package lspay

// DelayTransConfirmRequest 延时分账交易确认 gateway/delaytrans/confirm。
// 成功后保存响应里的 confirm_order_id，交易确认退款要用它。
type DelayTransConfirmRequest struct {
	PayOrderID string
	MchOrderNo string
	OrderID    string // 商户确认流水号，唯一
	// ChannelExtra 接受 *DivisionChannelExtra、map 或 JSON 字符串。
	ChannelExtra interface{}
	ExtendInfo   map[string]string
}

func (r *DelayTransConfirmRequest) Endpoint() string { return EndpointDelayTransConfirm }
func (r *DelayTransConfirmRequest) Validate() error  { return validate(r) }

func (r *DelayTransConfirmRequest) Params() (map[string]string, error) {
	if err := requireOne("pay_order_id", r.PayOrderID, "mch_order_no", r.MchOrderNo, 30); err != nil {
		return nil, err
	}
	if err := requiredString("order_id", r.OrderID, 30); err != nil {
		return nil, err
	}
	extraJSON, extra, err := normalizeJSONObject("channel_extra", r.ChannelExtra)
	if err != nil {
		return nil, err
	}
	if err := checkJSONLength("channel_extra", extraJSON, 256); err != nil {
		return nil, err
	}
	if v, ok := extra["div_list"]; ok {
		if err := validateDivisionList(v, true); err != nil {
			return nil, err
		}
	}
	p := params{}
	p.set("pay_order_id", r.PayOrderID)
	p.set("mch_order_no", r.MchOrderNo)
	p.set("order_id", r.OrderID)
	p.set("channel_extra", extraJSON)
	return p.finish(r.ExtendInfo)
}

// DelayTransConfirmRefundRequest 交易确认退款 gateway/delaytrans/confirmrefund。
// 延时分账订单给用户退款时，先调本接口，成功后再调 RefundRequest。
type DelayTransConfirmRefundRequest struct {
	// ConfirmOrderID 是交易确认响应返回的 confirm_order_id，不是确认请求里的 order_id。
	ConfirmOrderID  string
	ConfirmRefundNo string // 商户退款流水号，唯一
	ChannelExtra    interface{}
	ExtendInfo      map[string]string
}

func (r *DelayTransConfirmRefundRequest) Endpoint() string { return EndpointDelayTransConfirmRefund }
func (r *DelayTransConfirmRefundRequest) Validate() error  { return validate(r) }

func (r *DelayTransConfirmRefundRequest) Params() (map[string]string, error) {
	if err := requiredString("confirm_order_id", r.ConfirmOrderID, 30); err != nil {
		return nil, err
	}
	if err := requiredString("confirm_refund_no", r.ConfirmRefundNo, 30); err != nil {
		return nil, err
	}
	extraJSON, _, err := normalizeJSONObject("channel_extra", r.ChannelExtra)
	if err != nil {
		return nil, err
	}
	if err := checkJSONLength("channel_extra", extraJSON, 256); err != nil {
		return nil, err
	}
	p := params{}
	p.set("confirm_order_id", r.ConfirmOrderID)
	p.set("confirm_refund_no", r.ConfirmRefundNo)
	p.set("channel_extra", extraJSON)
	return p.finish(r.ExtendInfo)
}

// DelayTransUnconfirmQueryRequest 可转余额查询 gateway/delaytrans/unconfirm/query，没有业务参数。
type DelayTransUnconfirmQueryRequest struct {
	ExtendInfo map[string]string
}

func (r *DelayTransUnconfirmQueryRequest) Endpoint() string { return EndpointDelayTransUnconfirmQuery }
func (r *DelayTransUnconfirmQueryRequest) Validate() error  { return validate(r) }

func (r *DelayTransUnconfirmQueryRequest) Params() (map[string]string, error) {
	return params{}.finish(r.ExtendInfo)
}

// DelayTransCreateTaskRequest 延时分账订单转账 gateway/delaytrans/createtask。
// 同步响应 status=PENDING 只表示任务已创建，结果以转账通知为准。
type DelayTransCreateTaskRequest struct {
	AccountNo   string
	AccountName string
	OrderNo     string // 商户流水号，唯一
	Amount      int64  // 分
	NotifyURL   string
	Remark      string
	ExtendInfo  map[string]string
}

func (r *DelayTransCreateTaskRequest) Endpoint() string { return EndpointDelayTransCreateTask }
func (r *DelayTransCreateTaskRequest) Validate() error  { return validate(r) }

func (r *DelayTransCreateTaskRequest) Params() (map[string]string, error) {
	for _, check := range []error{
		requiredString("account_no", r.AccountNo, 30),
		requiredString("account_name", r.AccountName, 30),
		requiredString("order_no", r.OrderNo, 30),
		checkHTTPURL("notify_url", r.NotifyURL, 64),
		optionalString("remark", r.Remark, 256),
	} {
		if check != nil {
			return nil, check
		}
	}
	if r.Amount <= 0 {
		return nil, validationError("amount 必须是大于 0 的整数（单位：分）")
	}
	p := params{}
	p.set("account_no", r.AccountNo)
	p.set("account_name", r.AccountName)
	p.set("order_no", r.OrderNo)
	p.setInt("amount", r.Amount)
	p.set("notify_url", r.NotifyURL)
	p.set("remark", r.Remark)
	return p.finish(r.ExtendInfo)
}
