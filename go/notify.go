package lspay

import (
	"fmt"
	"mime"
	"net/http"
	"strconv"
	"strings"
)

// 通知应答。事务提交后返回 HTTP 200 和纯文本 "ok"；处理失败返回 "fail"，平台会重试。
const (
	NotifySuccessResponse = "ok"
	NotifyFailureResponse = "fail"
)

const maxNotifyBytes = 1 << 20

// Notify 是验签通过的异步通知。用类型断言区分 *PaymentNotify、*RefundNotify、*DelayTransTransferNotify。
type Notify interface {
	// IdempotencyKey 是去重用的唯一键，已处理过的通知直接返回 "ok"。
	IdempotencyKey() string
	IsSucceeded() bool
	Raw() map[string]string
}

// ReadNotifyForm 读取通知的全部表单字段。支付和退款通知是 multipart/form-data，
// 转账通知是 application/x-www-form-urlencoded。同名字段出现多次时返回校验错误。
func ReadNotifyForm(r *http.Request) (map[string]string, error) {
	r.Body = http.MaxBytesReader(nil, r.Body, maxNotifyBytes)
	mediaType, _, _ := mime.ParseMediaType(r.Header.Get("Content-Type"))
	var values map[string][]string
	if mediaType == "multipart/form-data" {
		if err := r.ParseMultipartForm(maxNotifyBytes); err != nil {
			return nil, &Error{Kind: ErrKindValidation, Message: "通知表单解析失败", Err: err}
		}
		values = r.MultipartForm.Value
	} else {
		if err := r.ParseForm(); err != nil {
			return nil, &Error{Kind: ErrKindValidation, Message: "通知表单解析失败", Err: err}
		}
		values = r.PostForm
	}
	form := make(map[string]string, len(values))
	for k, v := range values {
		if len(v) != 1 {
			return nil, validationError("通知参数 %s 重复", k)
		}
		form[k] = v[0]
	}
	return form, nil
}

// ParseNotify 验签并识别通知类型。cfg 必须按回调地址确定，不要根据通知里的 mch_no 选密钥。
func ParseNotify(form map[string]string, cfg MerchantConfig) (Notify, error) {
	notifyType, eventType := form["notify_type"], form["event_type"]
	switch {
	case notifyType == "refund" || strings.HasPrefix(eventType, "refund."):
		n, err := ParseRefundNotify(form, cfg)
		if err != nil {
			return nil, err
		}
		return n, nil
	case strings.HasPrefix(notifyType, "payment") || strings.HasPrefix(eventType, "payment."):
		n, err := ParsePaymentNotify(form, cfg)
		if err != nil {
			return nil, err
		}
		return n, nil
	}
	for _, key := range []string{"task_id", "order_no", "target_amount", "processed_amount", "remaining_amount"} {
		if _, ok := form[key]; !ok {
			return nil, validationError("无法根据 notify_type / event_type 识别通知类型")
		}
	}
	n, err := ParseDelayTransTransferNotify(form, cfg)
	if err != nil {
		return nil, err
	}
	return n, nil
}

// ParseNotify 用当前商户的凭证验签。
func (c *Client) ParseNotify(form map[string]string) (Notify, error) {
	return ParseNotify(form, c.config)
}

type baseNotify struct{ data map[string]string }

func newBaseNotify(form map[string]string, cfg MerchantConfig) (baseNotify, error) {
	if len(form) == 0 {
		return baseNotify{}, validationError("通知参数不能为空")
	}
	data := make(map[string]string, len(form))
	for k, v := range form {
		if k == "" {
			return baseNotify{}, validationError("通知参数名不能为空")
		}
		data[k] = v
	}
	if !md5Pattern.MatchString(data["sign"]) {
		return baseNotify{}, signError("通知缺少合法的 32 位 MD5 签名")
	}
	if !VerifySign(data, cfg.MD5Key) {
		return baseNotify{}, signError("通知签名验证失败")
	}
	n := baseNotify{data: data}
	mchNo, err := n.required("mch_no", 30)
	if err != nil {
		return n, err
	}
	appID, err := n.required("app_id", 24)
	if err != nil {
		return n, err
	}
	if !constantTimeEqual(cfg.MchNo, mchNo) || !constantTimeEqual(cfg.AppID, appID) {
		return n, validationError("通知商户身份与 SDK 配置不匹配")
	}
	return n, nil
}

// Raw 返回收到的全部字段。
func (n baseNotify) Raw() map[string]string {
	out := make(map[string]string, len(n.data))
	for k, v := range n.data {
		out[k] = v
	}
	return out
}

// Value 返回任意字段，包括 SDK 尚未声明的新字段。
func (n baseNotify) Value(key string) string { return n.data[key] }

func (n baseNotify) NotifyType() string  { return n.data["notify_type"] }
func (n baseNotify) EventType() string   { return n.data["event_type"] }
func (n baseNotify) PayOrderID() string  { return n.data["pay_order_id"] }
func (n baseNotify) MchNo() string       { return n.data["mch_no"] }
func (n baseNotify) AppID() string       { return n.data["app_id"] }
func (n baseNotify) WayCode() string     { return n.data["way_code"] }
func (n baseNotify) Currency() string    { return n.data["currency"] }
func (n baseNotify) ExtParam() string    { return n.data["ext_param"] }
func (n baseNotify) AddTime() string     { return n.data["add_time"] }
func (n baseNotify) SuccessTime() string { return n.data["success_time"] }
func (n baseNotify) ReqTime() string     { return n.data["req_time"] }

// State 返回 state，第二个返回值表示字段存在且是整数。
func (n baseNotify) State() (int, bool) {
	v, err := strconv.Atoi(n.data["state"])
	return v, err == nil && digitsPattern.MatchString(n.data["state"])
}

func (n baseNotify) required(field string, max int) (string, error) {
	v, ok := n.data[field]
	if !ok {
		return "", validationError("通知缺少 %s", field)
	}
	if strings.TrimSpace(v) == "" {
		return "", validationError("通知字段 %s 不能为空", field)
	}
	if textLen(v) > max {
		return "", validationError("通知字段 %s 长度超限", field)
	}
	return v, nil
}

func (n baseNotify) positive(field string) (int64, error) {
	v, err := n.nonNegative(field)
	if err == nil && v <= 0 {
		err = validationError("通知字段 %s 必须是大于 0 的整数", field)
	}
	return v, err
}

func (n baseNotify) nonNegative(field string) (int64, error) {
	s, err := n.required(field, 20)
	if err != nil {
		return 0, err
	}
	v, convErr := strconv.ParseInt(s, 10, 64)
	if convErr != nil || !digitsPattern.MatchString(s) {
		return 0, validationError("通知字段 %s 必须是非负整数", field)
	}
	return v, nil
}

func (n baseNotify) digits(field string, min, max int) error {
	s, err := n.required(field, max)
	if err != nil {
		return err
	}
	if !digitsPattern.MatchString(s) || len(s) < min {
		span := strconv.Itoa(min)
		if min != max {
			span = fmt.Sprintf("%d-%d", min, max)
		}
		return validationError("通知字段 %s 必须是 %s 位数字", field, span)
	}
	return nil
}

func (n baseNotify) currency() error {
	s, err := n.required("currency", 3)
	if err != nil {
		return err
	}
	if !currencyPattern.MatchString(s) {
		return validationError("通知字段 currency 必须是 3 位字母货币代码")
	}
	return nil
}

func (n baseNotify) stateIn(max int) error {
	if v, ok := n.State(); !ok || v < 0 || v > max {
		return validationError("通知字段 state 非法")
	}
	return nil
}

func (n baseNotify) eventPrefix(prefix string) error {
	for _, v := range []string{n.NotifyType(), n.EventType()} {
		if v != "" && !strings.HasPrefix(v, prefix) {
			return validationError("通知类型不匹配")
		}
	}
	return nil
}

func (n baseNotify) assertExpected(field, expected string) error {
	actual, ok := n.data[field]
	if !ok || !constantTimeEqual(expected, actual) {
		return validationError("通知字段 %s 与商户订单记录不一致", field)
	}
	return nil
}

// PaymentNotify 支付通知。
type PaymentNotify struct{ baseNotify }

// ParsePaymentNotify 验签并解析支付通知。
func ParsePaymentNotify(form map[string]string, cfg MerchantConfig) (*PaymentNotify, error) {
	base, err := newBaseNotify(form, cfg)
	if err != nil {
		return nil, err
	}
	n := &PaymentNotify{base}
	for _, f := range []struct {
		name string
		max  int
	}{{"pay_order_id", 30}, {"mch_order_no", 30}, {"way_code", 30}, {"subject", 64}, {"body", 256}} {
		if _, err := n.required(f.name, f.max); err != nil {
			return nil, err
		}
	}
	if _, err := n.positive("amount"); err != nil {
		return nil, err
	}
	for _, err := range []error{
		n.currency(),
		n.stateIn(OrderStateClosed),
		n.digits("add_time", 10, 10),
		n.digits("req_time", 10, 13),
		checkIPv4("通知字段 client_ip", n.data["client_ip"]),
		n.eventPrefix("payment"),
	} {
		if err != nil {
			return nil, err
		}
	}
	if n.data["success_time"] != "" {
		if err := n.digits("success_time", 10, 10); err != nil {
			return nil, err
		}
	}
	return n, nil
}

func (n *PaymentNotify) MchOrderNo() string { return n.data["mch_order_no"] }

// Amount 返回订单金额（分）。
func (n *PaymentNotify) Amount() int64 {
	v, _ := strconv.ParseInt(n.data["amount"], 10, 64)
	return v
}

// IsSucceeded 判断支付成功：state == 2 且 event_type 为空或为 payment.succeeded。
func (n *PaymentNotify) IsSucceeded() bool {
	state, ok := n.State()
	event := n.EventType()
	return ok && state == OrderStatePaid && (event == "" || event == "payment.succeeded")
}

// AssertOrder 核对单号、金额（分）、币种与本地订单一致。currency 为空时按 cny 核对。
func (n *PaymentNotify) AssertOrder(mchOrderNo string, amount int64, currency string) error {
	if currency == "" {
		currency = "cny"
	}
	for _, err := range []error{
		n.assertExpected("mch_order_no", mchOrderNo),
		n.assertExpected("amount", strconv.FormatInt(amount, 10)),
		n.assertExpected("currency", strings.ToLower(currency)),
	} {
		if err != nil {
			return err
		}
	}
	return nil
}

func (n *PaymentNotify) IdempotencyKey() string {
	event := n.EventType()
	if event == "" {
		event = n.data["state"]
	}
	return "payment:" + n.PayOrderID() + ":" + event
}

// RefundNotify 退款通知。时间字段是 yyyy-MM-dd HH:mm:ss。
type RefundNotify struct{ baseNotify }

// ParseRefundNotify 验签并解析退款通知。
func ParseRefundNotify(form map[string]string, cfg MerchantConfig) (*RefundNotify, error) {
	base, err := newBaseNotify(form, cfg)
	if err != nil {
		return nil, err
	}
	n := &RefundNotify{base}
	if n.NotifyType() != "refund" {
		return nil, validationError("通知字段 notify_type 必须是 refund")
	}
	if _, err := n.required("event_type", 64); err != nil {
		return nil, err
	}
	for _, f := range []string{"pay_order_id", "mch_refund_no", "way_code"} {
		if _, err := n.required(f, 30); err != nil {
			return nil, err
		}
	}
	payAmount, err := n.positive("pay_amount")
	if err != nil {
		return nil, err
	}
	refundAmount, err := n.positive("refund_amount")
	if err != nil {
		return nil, err
	}
	if refundAmount > payAmount {
		return nil, validationError("通知字段 refund_amount 不能大于 pay_amount")
	}
	for _, err := range []error{
		n.eventPrefix("refund"),
		n.currency(),
		n.stateIn(RefundStateClosed),
		n.digits("req_time", 10, 13),
	} {
		if err != nil {
			return nil, err
		}
	}
	addTime, err := n.required("add_time", 19)
	if err != nil {
		return nil, err
	}
	if err := checkDateTime("通知字段 add_time", addTime); err != nil {
		return nil, err
	}
	if s := n.SuccessTime(); s != "" {
		if err := checkDateTime("通知字段 success_time", s); err != nil {
			return nil, err
		}
	}
	return n, nil
}

func (n *RefundNotify) MchRefundNo() string { return n.data["mch_refund_no"] }

func (n *RefundNotify) PayAmount() int64 {
	v, _ := strconv.ParseInt(n.data["pay_amount"], 10, 64)
	return v
}

func (n *RefundNotify) RefundAmount() int64 {
	v, _ := strconv.ParseInt(n.data["refund_amount"], 10, 64)
	return v
}

// IsSucceeded 判断退款成功：state == 2 且 event_type 为 refund.succeeded。
func (n *RefundNotify) IsSucceeded() bool {
	state, ok := n.State()
	return ok && state == RefundStateSucceeded && n.EventType() == "refund.succeeded"
}

// AssertRefund 核对退款单号、退款金额（分）和币种。payAmount 大于 0 时同时核对原订单金额。
func (n *RefundNotify) AssertRefund(mchRefundNo string, refundAmount int64, currency string, payAmount int64) error {
	if currency == "" {
		currency = "cny"
	}
	checks := []error{
		n.assertExpected("mch_refund_no", mchRefundNo),
		n.assertExpected("refund_amount", strconv.FormatInt(refundAmount, 10)),
		n.assertExpected("currency", strings.ToLower(currency)),
	}
	if payAmount > 0 {
		checks = append(checks, n.assertExpected("pay_amount", strconv.FormatInt(payAmount, 10)))
	}
	for _, err := range checks {
		if err != nil {
			return err
		}
	}
	return nil
}

func (n *RefundNotify) IdempotencyKey() string {
	return "refund:" + n.MchRefundNo() + ":" + n.EventType()
}

// DelayTransTransferNotify 延时分账订单转账通知，同一任务会随进度多次推送。
type DelayTransTransferNotify struct{ baseNotify }

// ParseDelayTransTransferNotify 验签并解析转账通知。
func ParseDelayTransTransferNotify(form map[string]string, cfg MerchantConfig) (*DelayTransTransferNotify, error) {
	base, err := newBaseNotify(form, cfg)
	if err != nil {
		return nil, err
	}
	n := &DelayTransTransferNotify{base}
	for _, f := range []struct {
		name string
		max  int
	}{{"task_id", 64}, {"order_no", 30}, {"account_no", 64}, {"account_name", 128}} {
		if _, err := n.required(f.name, f.max); err != nil {
			return nil, err
		}
	}
	target, err := n.positive("target_amount")
	if err != nil {
		return nil, err
	}
	processed, err := n.nonNegative("processed_amount")
	if err != nil {
		return nil, err
	}
	remaining, err := n.nonNegative("remaining_amount")
	if err != nil {
		return nil, err
	}
	status, err := n.required("status", 16)
	if err != nil {
		return nil, err
	}
	if !taskStatuses[status] {
		return nil, validationError("通知字段 status 非法")
	}
	if processed > target || remaining > target {
		return nil, validationError("通知金额不能超过 target_amount")
	}
	progressText, err := n.required("progress", 8)
	if err != nil {
		return nil, err
	}
	progress, convErr := strconv.ParseFloat(progressText, 64)
	if convErr != nil || progress < 0 || progress > 100 {
		return nil, validationError("通知字段 progress 必须是 0.0-100.0 的数字")
	}
	switch status {
	case TaskStatusPending, TaskStatusFailed:
		if processed != 0 || remaining != target {
			return nil, validationError("PENDING/FAILED 状态的通知金额关系非法")
		}
	case TaskStatusProcessing, TaskStatusPartial:
		if processed <= 0 || processed >= target || remaining != target-processed {
			return nil, validationError("PROCESSING/PARTIAL 状态的通知金额关系非法")
		}
	}
	return n, nil
}

func (n *DelayTransTransferNotify) TaskID() string      { return n.data["task_id"] }
func (n *DelayTransTransferNotify) OrderNo() string     { return n.data["order_no"] }
func (n *DelayTransTransferNotify) TaskStatus() string  { return n.data["status"] }
func (n *DelayTransTransferNotify) AccountNo() string   { return n.data["account_no"] }
func (n *DelayTransTransferNotify) AccountName() string { return n.data["account_name"] }

func (n *DelayTransTransferNotify) TargetAmount() int64 {
	v, _ := strconv.ParseInt(n.data["target_amount"], 10, 64)
	return v
}

// ProcessedAmount 是已处理金额（分）。最终到账金额以 COMPLETED 时的值为准。
func (n *DelayTransTransferNotify) ProcessedAmount() int64 {
	v, _ := strconv.ParseInt(n.data["processed_amount"], 10, 64)
	return v
}

func (n *DelayTransTransferNotify) RemainingAmount() int64 {
	v, _ := strconv.ParseInt(n.data["remaining_amount"], 10, 64)
	return v
}

func (n *DelayTransTransferNotify) IsSucceeded() bool { return n.TaskStatus() == TaskStatusCompleted }
func (n *DelayTransTransferNotify) IsPartial() bool   { return n.TaskStatus() == TaskStatusPartial }

// AssertTask 核对商户流水号和目标金额（分）。
func (n *DelayTransTransferNotify) AssertTask(orderNo string, targetAmount int64) error {
	if err := n.assertExpected("order_no", orderNo); err != nil {
		return err
	}
	return n.assertExpected("target_amount", strconv.FormatInt(targetAmount, 10))
}

func (n *DelayTransTransferNotify) IdempotencyKey() string {
	return fmt.Sprintf("delaytrans-transfer:%s:%s:%s", n.TaskID(), n.TaskStatus(), n.data["processed_amount"])
}
