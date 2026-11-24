package lspay

import "regexp"

var (
	bindVerifyCodePattern = regexp.MustCompile(`^\d{3,8}$`)
	payVerifyCodePattern  = regexp.MustCompile(`^\d{6}$`)
)

// QuickPayBindApplyRequest 快捷支付绑卡申请 gateway/quickpay/buckleapply，way_code 固定 BANK_FAST。
// 成功后银行向预留手机号发验证码。
type QuickPayBindApplyRequest struct {
	OrderID  string // 绑卡确认时复用
	ClientIP string
	// ChannelExtra 接受 *QuickPayBindChannelExtra、map 或 JSON 字符串。
	ChannelExtra interface{}
	ExtendInfo   map[string]string
}

func (r *QuickPayBindApplyRequest) Endpoint() string { return EndpointQuickPayBindApply }
func (r *QuickPayBindApplyRequest) Validate() error  { return validate(r) }

func (r *QuickPayBindApplyRequest) Params() (map[string]string, error) {
	if err := quickPayCommon(r.OrderID, r.ClientIP); err != nil {
		return nil, err
	}
	_, extra, err := normalizeJSONObject("channel_extra", r.ChannelExtra)
	if err != nil {
		return nil, err
	}
	if err := validateQuickPayBind(extra); err != nil {
		return nil, err
	}
	extraJSON, err := reencode("channel_extra", extra)
	if err != nil {
		return nil, err
	}
	if err := checkJSONLength("channel_extra", extraJSON, 256); err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeBankFast}
	p.set("order_id", r.OrderID)
	p.set("client_ip", r.ClientIP)
	p.set("channel_extra", extraJSON)
	return p.finish(r.ExtendInfo)
}

// QuickPayBindConfirmRequest 快捷支付绑卡确认 gateway/quickpay/buckleconfirm。
// 只有响应 HasBinding() 为 true 时才保存 channel_user 与 channel_card_id。
type QuickPayBindConfirmRequest struct {
	OrderID      string // 与绑卡申请相同
	ClientIP     string
	ChannelExtra interface{} // *QuickPayBindConfirmChannelExtra、map 或 JSON 字符串
	VerifyCode   string      // 短信验证码，3–8 位数字
	ExtendInfo   map[string]string
}

func (r *QuickPayBindConfirmRequest) Endpoint() string { return EndpointQuickPayBindConfirm }
func (r *QuickPayBindConfirmRequest) Validate() error  { return validate(r) }

func (r *QuickPayBindConfirmRequest) Params() (map[string]string, error) {
	if err := quickPayCommon(r.OrderID, r.ClientIP); err != nil {
		return nil, err
	}
	_, extra, err := normalizeJSONObject("channel_extra", r.ChannelExtra)
	if err != nil {
		return nil, err
	}
	if err := validateQuickPayBindConfirm(extra); err != nil {
		return nil, err
	}
	if !bindVerifyCodePattern.MatchString(r.VerifyCode) {
		return nil, validationError("verify_code 必须是 3-8 位数字")
	}
	extraJSON, err := reencode("channel_extra", extra)
	if err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeBankFast}
	p.set("order_id", r.OrderID)
	p.set("client_ip", r.ClientIP)
	p.set("channel_extra", extraJSON)
	p.set("verify_code", r.VerifyCode)
	return p.finish(r.ExtendInfo)
}

// QuickPayConfirmRequest 快捷支付确认 gateway/quickpay/payconfirm。
// trans_status=P 表示仍在验证，结果以支付通知或订单查询为准。
type QuickPayConfirmRequest struct {
	PayOrderID string
	MchOrderNo string
	VerifyCode string // 6 位数字
	ClientIP   string
	ExtendInfo map[string]string
}

func (r *QuickPayConfirmRequest) Endpoint() string { return EndpointQuickPayConfirm }
func (r *QuickPayConfirmRequest) Validate() error  { return validate(r) }

func (r *QuickPayConfirmRequest) Params() (map[string]string, error) {
	return quickPayVerifyParams(r.PayOrderID, r.MchOrderNo, r.VerifyCode, r.ClientIP, r.ExtendInfo)
}

// QuickPaySmsCheckRequest 快捷支付短信校验 gateway/quickpay/smscheck。
// 只预校验验证码，成功后仍要调 QuickPayConfirmRequest。
type QuickPaySmsCheckRequest struct {
	PayOrderID string
	MchOrderNo string
	VerifyCode string // 6 位数字
	ClientIP   string
	ExtendInfo map[string]string
}

func (r *QuickPaySmsCheckRequest) Endpoint() string { return EndpointQuickPaySmsCheck }
func (r *QuickPaySmsCheckRequest) Validate() error  { return validate(r) }

func (r *QuickPaySmsCheckRequest) Params() (map[string]string, error) {
	return quickPayVerifyParams(r.PayOrderID, r.MchOrderNo, r.VerifyCode, r.ClientIP, r.ExtendInfo)
}

// QuickPayUnbindRequest 快捷支付解绑 gateway/quickpay/payunbind。
type QuickPayUnbindRequest struct {
	OrderID      string
	ClientIP     string
	ChannelExtra interface{} // *QuickPayUnbindChannelExtra、map 或 JSON 字符串
	ExtendInfo   map[string]string
}

func (r *QuickPayUnbindRequest) Endpoint() string { return EndpointQuickPayUnbind }
func (r *QuickPayUnbindRequest) Validate() error  { return validate(r) }

func (r *QuickPayUnbindRequest) Params() (map[string]string, error) {
	if err := quickPayCommon(r.OrderID, r.ClientIP); err != nil {
		return nil, err
	}
	extraJSON, extra, err := normalizeJSONObject("channel_extra", r.ChannelExtra)
	if err != nil {
		return nil, err
	}
	if err := requiredFields(extra, "channel_extra", "mch_user_id", "channel_card_id"); err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeBankFast}
	p.set("order_id", r.OrderID)
	p.set("client_ip", r.ClientIP)
	p.set("channel_extra", extraJSON)
	return p.finish(r.ExtendInfo)
}

func quickPayCommon(orderID, clientIP string) error {
	if err := requiredString("order_id", orderID, 30); err != nil {
		return err
	}
	return checkIPv4("client_ip", clientIP)
}

func quickPayVerifyParams(payOrderID, mchOrderNo, verifyCode, clientIP string, extend map[string]string) (map[string]string, error) {
	if err := requireOne("pay_order_id", payOrderID, "mch_order_no", mchOrderNo, 30); err != nil {
		return nil, err
	}
	if !payVerifyCodePattern.MatchString(verifyCode) {
		return nil, validationError("verify_code 必须是 6 位数字")
	}
	if err := checkIPv4("client_ip", clientIP); err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeBankFast}
	p.set("pay_order_id", payOrderID)
	p.set("mch_order_no", mchOrderNo)
	p.set("verify_code", verifyCode)
	p.set("client_ip", clientIP)
	return p.finish(extend)
}
