package lspay

import "strings"

// UnifiedOrderRequest 统一下单 gateway/unifiedorder。
type UnifiedOrderRequest struct {
	MchOrderNo string
	WayCode    string
	Amount     int64  // 分
	Currency   string // 为空时为 cny
	Subject    string
	Body       string
	// ChannelExtra 接受 *UnifiedOrderChannelExtra、map[string]interface{} 或 JSON 对象字符串。
	ChannelExtra interface{}
	ClientIP     string
	// NotifyURL 为空时使用 MerchantConfig.NotifyURL；两者都为空时平台不会发支付通知。
	NotifyURL   string
	ReturnURL   string
	ExpiredTime int // 订单有效期（秒），0 表示平台默认 7200
	// DivisionMode：0 不分账、1 实时分账、2 延时分账。1、2 时 channel_extra.div_list 必填。
	DivisionMode int
	ExtParam     string // 回调原样返回
	// PollingMode：0 关闭、1 自然轮询、2 权重轮询、3 防断链轮询。
	PollingMode int
	ExtendInfo  map[string]string
}

func (r *UnifiedOrderRequest) Endpoint() string { return EndpointUnifiedOrder }
func (r *UnifiedOrderRequest) Validate() error  { return validate(r) }

func (r *UnifiedOrderRequest) Params() (map[string]string, error) {
	p, _, err := r.build(IsPaymentMethod)
	if err != nil {
		return nil, err
	}
	return p.finish(r.ExtendInfo)
}

func (r *UnifiedOrderRequest) build(allowed func(string) bool) (params, map[string]interface{}, error) {
	if err := requiredString("mch_order_no", r.MchOrderNo, 30); err != nil {
		return nil, nil, err
	}
	if err := requiredString("way_code", r.WayCode, 30); err != nil {
		return nil, nil, err
	}
	if !allowed(r.WayCode) {
		return nil, nil, validationError("way_code 不在统一下单支付方式清单中")
	}
	if r.Amount <= 0 {
		return nil, nil, validationError("amount 必须是大于 0 的整数（单位：分）")
	}
	currency, err := normalizeCurrency(r.Currency)
	if err != nil {
		return nil, nil, err
	}
	for _, check := range []error{
		requiredString("subject", r.Subject, 64),
		requiredString("body", r.Body, 256),
		optionalString("client_ip", r.ClientIP, 32),
		checkIPv4("client_ip", r.ClientIP),
		optionalString("ext_param", r.ExtParam, 512),
		checkHTTPURL("notify_url", r.NotifyURL, 128),
		checkHTTPURL("return_url", r.ReturnURL, 128),
		checkRange("division_mode", r.DivisionMode, 0, 2),
		checkRange("polling_mode", r.PollingMode, 0, 3),
	} {
		if check != nil {
			return nil, nil, check
		}
	}
	if r.ExpiredTime < 0 {
		return nil, nil, validationError("expired_time 必须是大于 0 的秒数")
	}

	extraJSON, extra, err := normalizeJSONObject("channel_extra", r.ChannelExtra)
	if err != nil {
		return nil, nil, err
	}
	if extraJSON == "" {
		return nil, nil, validationError("channel_extra 必填")
	}
	required := map[string]string{
		WayCodeAliLite: "buyer_id", WayCodeWxLite: "openid",
		WayCodeWxBar: "auth_code", WayCodeAliBar: "auth_code", WayCodeYsfBar: "auth_code",
	}
	if strings.TrimSpace(objectString(extra, "pay_data_type")) == "" {
		required[WayCodeAliPub] = "buyer_id"
		required[WayCodeWxPub] = "openid"
	}
	if field := required[r.WayCode]; field != "" && strings.TrimSpace(objectString(extra, field)) == "" {
		return nil, nil, validationError("way_code=%s 时 channel_extra.%s 必填", r.WayCode, field)
	}
	if r.WayCode == WayCodeBankFast {
		if err := requiredFields(extra, "channel_extra", "channel_user", "channel_card_id"); err != nil {
			return nil, nil, err
		}
	}
	if v, ok := extra["pay_data_type"]; ok {
		if s, _ := scalarString(v); !payDataTypes[s] {
			return nil, nil, validationError("channel_extra.pay_data_type 非法")
		}
	}
	if v, ok := extra["div_list"]; ok {
		if err := validateDivisionList(v, false); err != nil {
			return nil, nil, err
		}
	}
	if r.DivisionMode == 1 || r.DivisionMode == 2 {
		if list, _ := extra["div_list"].([]interface{}); len(list) == 0 {
			return nil, nil, validationError("division_mode 为 1 或 2 时 channel_extra.div_list 必填且不能为空")
		}
	}

	p := params{}
	p.set("mch_order_no", r.MchOrderNo)
	p.set("way_code", r.WayCode)
	p.setInt("amount", r.Amount)
	p.set("currency", currency)
	p.set("subject", r.Subject)
	p.set("body", r.Body)
	p.set("channel_extra", extraJSON)
	p.set("client_ip", r.ClientIP)
	p.set("notify_url", r.NotifyURL)
	p.set("return_url", r.ReturnURL)
	p.setInt("expired_time", int64(r.ExpiredTime))
	p.setInt("division_mode", int64(r.DivisionMode))
	p.set("ext_param", r.ExtParam)
	p.setInt("polling_mode", int64(r.PollingMode))
	return p, extra, nil
}

// QuickPayApplyRequest 快捷支付申请，走 gateway/unifiedorder，way_code 固定 BANK_FAST。
// channel_extra 必须带绑卡确认返回的 channel_user 和 channel_card_id。
type QuickPayApplyRequest struct {
	MchOrderNo   string
	Amount       int64
	Currency     string
	Subject      string
	Body         string
	ChannelExtra interface{} // *UnifiedOrderChannelExtra、map 或 JSON 字符串
	ClientIP     string
	NotifyURL    string
	ReturnURL    string
	ExpiredTime  int
	DivisionMode int
	ExtParam     string
	ExtendInfo   map[string]string
}

func (r *QuickPayApplyRequest) Endpoint() string { return EndpointUnifiedOrder }
func (r *QuickPayApplyRequest) Validate() error  { return validate(r) }

func (r *QuickPayApplyRequest) Params() (map[string]string, error) {
	u := UnifiedOrderRequest{
		MchOrderNo: r.MchOrderNo, WayCode: WayCodeBankFast, Amount: r.Amount, Currency: r.Currency,
		Subject: r.Subject, Body: r.Body, ChannelExtra: r.ChannelExtra, ClientIP: r.ClientIP,
		NotifyURL: r.NotifyURL, ReturnURL: r.ReturnURL, ExpiredTime: r.ExpiredTime,
		DivisionMode: r.DivisionMode, ExtParam: r.ExtParam,
	}
	p, _, err := u.build(IsPaymentMethod)
	if err != nil {
		return nil, err
	}
	return p.finish(r.ExtendInfo)
}

// BalancePayRequest 余额支付，走 gateway/unifiedorder，way_code 固定 BALANCE_PAY，division_mode 固定 1。
type BalancePayRequest struct {
	MchOrderNo string
	Amount     int64
	Currency   string
	Subject    string
	Body       string
	// ChannelExtra 接受 *BalancePayChannelExtra、map 或 JSON 字符串。每个 div_amt 必填，总和不能超过 Amount。
	ChannelExtra interface{}
	ClientIP     string
	NotifyURL    string
	ReturnURL    string
	ExpiredTime  int
	ExtParam     string
	ExtendInfo   map[string]string
}

func (r *BalancePayRequest) Endpoint() string { return EndpointUnifiedOrder }
func (r *BalancePayRequest) Validate() error  { return validate(r) }

func (r *BalancePayRequest) Params() (map[string]string, error) {
	u := UnifiedOrderRequest{
		MchOrderNo: r.MchOrderNo, WayCode: WayCodeBalancePay, Amount: r.Amount, Currency: r.Currency,
		Subject: r.Subject, Body: r.Body, ChannelExtra: r.ChannelExtra, ClientIP: r.ClientIP,
		NotifyURL: r.NotifyURL, ReturnURL: r.ReturnURL, ExpiredTime: r.ExpiredTime,
		DivisionMode: 1, ExtParam: r.ExtParam,
	}
	p, extra, err := u.build(func(code string) bool { return code == WayCodeBalancePay })
	if err != nil {
		return nil, err
	}
	if _, err := requiredField(extra, "trans_out", "channel_extra.trans_out", 256); err != nil {
		return nil, err
	}
	var total int64
	for _, item := range objectList(extra["div_list"]) {
		amount, ok := strictInt(item["div_amt"])
		if !ok || amount <= 0 {
			return nil, validationError("余额支付 div_amt 必填且必须为正整数")
		}
		total += amount
	}
	if total > r.Amount {
		return nil, validationError("channel_extra.div_list 的 div_amt 总和不能超过 amount")
	}
	return p.finish(r.ExtendInfo)
}

// QueryOrderRequest 订单查询 gateway/query，两个单号二选一。
type QueryOrderRequest struct {
	MchOrderNo string
	PayOrderID string
	ExtendInfo map[string]string
}

func (r *QueryOrderRequest) Endpoint() string { return EndpointQueryOrder }
func (r *QueryOrderRequest) Validate() error  { return validate(r) }

func (r *QueryOrderRequest) Params() (map[string]string, error) {
	if err := requireOne("mch_order_no", r.MchOrderNo, "pay_order_id", r.PayOrderID, 30); err != nil {
		return nil, err
	}
	p := params{}
	p.set("mch_order_no", r.MchOrderNo)
	p.set("pay_order_id", r.PayOrderID)
	return p.finish(r.ExtendInfo)
}

// RefundRequest 申请退款 gateway/refund。重试同一笔退款时复用同一个 MchRefundNo。
type RefundRequest struct {
	MchRefundNo  string
	MchOrderNo   string
	PayOrderID   string
	RefundAmount int64 // 分
	Currency     string
	RefundReason string
	ClientIP     string
	// NotifyURL 为空时平台不会发退款通知。
	NotifyURL string
	// ChannelExtra 接受 *DivisionChannelExtra、map 或 JSON 字符串，已分账订单用来指定退回的接收方。
	ChannelExtra interface{}
	ExtParam     string
	ExtendInfo   map[string]string
}

func (r *RefundRequest) Endpoint() string { return EndpointRefund }
func (r *RefundRequest) Validate() error  { return validate(r) }

func (r *RefundRequest) Params() (map[string]string, error) {
	if err := requiredString("mch_refund_no", r.MchRefundNo, 30); err != nil {
		return nil, err
	}
	if err := requireOne("mch_order_no", r.MchOrderNo, "pay_order_id", r.PayOrderID, 30); err != nil {
		return nil, err
	}
	if r.RefundAmount <= 0 {
		return nil, validationError("refund_amount 必须是大于 0 的整数（单位：分）")
	}
	currency, err := normalizeCurrency(r.Currency)
	if err != nil {
		return nil, err
	}
	for _, check := range []error{
		requiredString("refund_reason", r.RefundReason, 64),
		checkIPv4("client_ip", r.ClientIP),
		checkHTTPURL("notify_url", r.NotifyURL, 128),
		optionalString("ext_param", r.ExtParam, 512),
	} {
		if check != nil {
			return nil, check
		}
	}
	extraJSON, extra, err := normalizeJSONObject("channel_extra", r.ChannelExtra)
	if err != nil {
		return nil, err
	}
	if err := checkJSONLength("channel_extra", extraJSON, 256); err != nil {
		return nil, err
	}
	if v, ok := extra["div_list"]; ok {
		if err := validateDivisionList(v, false); err != nil {
			return nil, err
		}
	}
	p := params{}
	p.set("mch_refund_no", r.MchRefundNo)
	p.set("mch_order_no", r.MchOrderNo)
	p.set("pay_order_id", r.PayOrderID)
	p.setInt("refund_amount", r.RefundAmount)
	p.set("currency", currency)
	p.set("refund_reason", r.RefundReason)
	p.set("client_ip", r.ClientIP)
	p.set("notify_url", r.NotifyURL)
	p.set("channel_extra", extraJSON)
	p.set("ext_param", r.ExtParam)
	return p.finish(r.ExtendInfo)
}

// QueryRefundRequest 退款查询 gateway/refund/query。优先用申请退款返回的 OrderRefundID，
// 申请超时没拿到时再用 MchRefundNo。
type QueryRefundRequest struct {
	OrderRefundID string
	MchRefundNo   string
	ExtendInfo    map[string]string
}

func (r *QueryRefundRequest) Endpoint() string { return EndpointQueryRefund }
func (r *QueryRefundRequest) Validate() error  { return validate(r) }

func (r *QueryRefundRequest) Params() (map[string]string, error) {
	if err := requireOne("order_refund_id", r.OrderRefundID, "mch_refund_no", r.MchRefundNo, 30); err != nil {
		return nil, err
	}
	p := params{}
	p.set("order_refund_id", r.OrderRefundID)
	p.set("mch_refund_no", r.MchRefundNo)
	return p.finish(r.ExtendInfo)
}
