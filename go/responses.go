package lspay

import (
	"fmt"
	"strings"
)

// UnifiedOrderResponse 统一下单响应。支付结果以支付通知或订单查询为准。
type UnifiedOrderResponse struct{ Response }

func (r *UnifiedOrderResponse) PayOrderID() string  { return r.String("pay_order_id") }
func (r *UnifiedOrderResponse) MchOrderNo() string  { return r.String("mch_order_no") }
func (r *UnifiedOrderResponse) TransStatus() string { return r.String("trans_status") }
func (r *UnifiedOrderResponse) PayDataType() string { return r.String("pay_data_type") }
func (r *UnifiedOrderResponse) ErrCode() string     { return r.String("err_code") }
func (r *UnifiedOrderResponse) ErrMsg() string      { return r.String("err_msg") }

// OrderState 返回 order_state，第二个返回值表示字段存在。
func (r *UnifiedOrderResponse) OrderState() (int, bool) { return r.state("order_state") }

// PayInfo 返回 pay_info 原文；pay_info 是对象时返回其 JSON。
func (r *UnifiedOrderResponse) PayInfo() string {
	v := r.Value("pay_info")
	if s, ok := scalarString(v); ok {
		return s
	}
	if v == nil {
		return ""
	}
	b, _ := marshalJSON(v)
	return string(b)
}

// PayInfoMap 把 pay_info 解析为对象，用于 WX_LITE、WX_LITE_H5、WX_LITE_JUMP 等返回 JSON 的场景。
func (r *UnifiedOrderResponse) PayInfoMap() map[string]interface{} {
	switch v := r.Value("pay_info").(type) {
	case map[string]interface{}:
		return v
	case string:
		if m, err := decodeJSONObject([]byte(v)); err == nil && m != nil {
			return m
		}
	}
	return map[string]interface{}{}
}

// IsAccepted 表示下单已受理（trans_status == "S"）。
func (r *UnifiedOrderResponse) IsAccepted() bool {
	return !r.IsError() && r.TransStatus() == TransStatusSucceeded
}

// IsPaid 表示下单即支付成功（order_state == 2），付款码、快捷支付、余额支付可能出现。
func (r *UnifiedOrderResponse) IsPaid() bool {
	state, ok := r.OrderState()
	return !r.IsError() && ok && state == OrderStatePaid
}

// QuickPayApplyResponse 快捷支付申请响应。trans_status == "P" 表示银行已发短信，不是失败。
type QuickPayApplyResponse struct{ UnifiedOrderResponse }

func (r *QuickPayApplyResponse) IsAccepted() bool {
	s := r.TransStatus()
	return !r.IsError() && (s == TransStatusSucceeded || s == TransStatusProcessing)
}

func (r *QuickPayApplyResponse) IsProcessing() bool {
	return !r.IsError() && r.TransStatus() == TransStatusProcessing
}

// BalancePayResponse 余额支付响应，IsPaid 为 true 表示已支付成功。
type BalancePayResponse struct{ UnifiedOrderResponse }

// QueryOrderResponse 订单查询响应。
type QueryOrderResponse struct{ Response }

func (r *QueryOrderResponse) PayOrderID() string     { return r.String("pay_order_id") }
func (r *QueryOrderResponse) MchNo() string          { return r.String("mch_no") }
func (r *QueryOrderResponse) AppID() string          { return firstNonEmpty(&r.Response, "app_id", "app_Id") }
func (r *QueryOrderResponse) MchOrderNo() string     { return r.String("mch_order_no") }
func (r *QueryOrderResponse) WayCode() string        { return r.String("way_code") }
func (r *QueryOrderResponse) Amount() int64          { return r.intOrZero("amount") }
func (r *QueryOrderResponse) Currency() string       { return r.String("currency") }
func (r *QueryOrderResponse) Subject() string        { return r.String("subject") }
func (r *QueryOrderResponse) Body() string           { return r.String("body") }
func (r *QueryOrderResponse) ChannelOrderNo() string { return r.String("channel_order_no") }
func (r *QueryOrderResponse) ExtParam() string       { return r.String("ext_param") }
func (r *QueryOrderResponse) ErrCode() string        { return r.String("err_code") }
func (r *QueryOrderResponse) ErrMsg() string         { return r.String("err_msg") }
func (r *QueryOrderResponse) MchFeeAmount() int64    { return r.intOrZero("mch_fee_amount") }
func (r *QueryOrderResponse) UnconfirmAmount() int64 { return r.intOrZero("unconfirm_amount") }
func (r *QueryOrderResponse) ConfirmedAmount() int64 { return r.intOrZero("confirmed_amount") }
func (r *QueryOrderResponse) FundFreezeStat() string { return r.String("fund_freeze_stat") }

// AddTime 和 SuccessTime 是时间戳，可能是 10 位秒或 13 位毫秒。
func (r *QueryOrderResponse) AddTime() int64     { return r.intOrZero("add_time") }
func (r *QueryOrderResponse) SuccessTime() int64 { return r.intOrZero("success_time") }

// State 返回订单状态 state（OrderState* 常量）。
func (r *QueryOrderResponse) State() (int, bool)         { return r.state("state") }
func (r *QueryOrderResponse) DivisionMode() (int, bool)  { return r.state("division_mode") }
func (r *QueryOrderResponse) DivisionState() (int, bool) { return r.state("division_state") }

func (r *QueryOrderResponse) IsPaid() bool {
	state, ok := r.State()
	return !r.IsError() && ok && state == OrderStatePaid
}

// RefundResponse 申请退款响应。保存 RefundOrderID，退款查询优先用它。
type RefundResponse struct{ Response }

func (r *RefundResponse) RefundOrderID() string {
	return firstNonEmpty(&r.Response, "refund_order_id", "order_refund_id", "order_refund_Id")
}
func (r *RefundResponse) MchRefundNo() string { return r.String("mch_refund_no") }
func (r *RefundResponse) ChannelOrderNo() string {
	return firstNonEmpty(&r.Response, "channel_order_no", "channel_rrder_no")
}
func (r *RefundResponse) PayAmount() int64    { return r.intOrZero("pay_amount") }
func (r *RefundResponse) RefundAmount() int64 { return r.intOrZero("refund_amount") }
func (r *RefundResponse) ErrCode() string     { return r.String("err_code") }
func (r *RefundResponse) ErrMsg() string      { return r.String("err_msg") }

// State 返回退款状态（RefundState* 常量）。
func (r *RefundResponse) State() (int, bool) { return r.state("state") }

func (r *RefundResponse) IsRefunded() bool {
	state, ok := r.State()
	return !r.IsError() && ok && state == RefundStateSucceeded
}

// QueryRefundResponse 退款查询响应。
type QueryRefundResponse struct{ RefundResponse }

func (r *QueryRefundResponse) PayOrderID() string { return r.String("pay_order_id") }
func (r *QueryRefundResponse) MchNo() string      { return r.String("mch_no") }
func (r *QueryRefundResponse) AppID() string      { return r.String("app_id") }
func (r *QueryRefundResponse) Currency() string   { return r.String("currency") }
func (r *QueryRefundResponse) ExtParam() string   { return r.String("ext_param") }
func (r *QueryRefundResponse) AddTime() int64     { return r.intOrZero("add_time") }
func (r *QueryRefundResponse) SuccessTime() int64 { return r.intOrZero("success_time") }

// DelayTransConfirmResponse 延时分账交易确认响应。必须 IsSuccessful 才算确认成功。
type DelayTransConfirmResponse struct{ Response }

func (r *DelayTransConfirmResponse) TransStatus() string    { return r.String("trans_status") }
func (r *DelayTransConfirmResponse) ConfirmOrderID() string { return r.String("confirm_order_id") }
func (r *DelayTransConfirmResponse) BatchOrderID() string   { return r.String("batch_order_id") }
func (r *DelayTransConfirmResponse) UnconfirmAmount() int64 { return r.intOrZero("unconfirm_amount") }
func (r *DelayTransConfirmResponse) ConfirmedAmount() int64 { return r.intOrZero("confirmed_amount") }
func (r *DelayTransConfirmResponse) ErrCode() string        { return r.String("err_code") }
func (r *DelayTransConfirmResponse) ErrMsg() string         { return r.String("err_msg") }

func (r *DelayTransConfirmResponse) IsSuccessful() bool {
	return !r.IsError() && r.TransStatus() == TransStatusSucceeded
}

// DelayTransConfirmRefundResponse 交易确认退款响应。
type DelayTransConfirmRefundResponse struct{ DelayTransConfirmResponse }

func (r *DelayTransConfirmRefundResponse) ConfirmRefundNo() string {
	return r.String("confirm_refund_no")
}

// DelayTransUnconfirmQueryResponse 可转余额查询响应。
type DelayTransUnconfirmQueryResponse struct{ Response }

// UnconfirmAmount 是所有延时分账订单的未分账总额（分）。
func (r *DelayTransUnconfirmQueryResponse) UnconfirmAmount() int64 {
	return r.intOrZero("unconfirm_amount")
}

// DelayTransCreateTaskResponse 延时分账订单转账响应。
type DelayTransCreateTaskResponse struct{ Response }

func (r *DelayTransCreateTaskResponse) TaskID() string         { return r.String("task_id") }
func (r *DelayTransCreateTaskResponse) OrderNo() string        { return r.String("order_no") }
func (r *DelayTransCreateTaskResponse) AccountNo() string      { return r.String("account_no") }
func (r *DelayTransCreateTaskResponse) AccountName() string    { return r.String("account_name") }
func (r *DelayTransCreateTaskResponse) Amount() int64          { return r.intOrZero("amount") }
func (r *DelayTransCreateTaskResponse) TaskStatus() string     { return objectString(r.Data(), "status") }
func (r *DelayTransCreateTaskResponse) SelectedOrders() int64  { return r.intOrZero("selected_orders") }
func (r *DelayTransCreateTaskResponse) FirstOrderTime() string { return r.String("first_order_time") }
func (r *DelayTransCreateTaskResponse) LastOrderTime() string  { return r.String("last_order_time") }

// IsCreated 表示任务已创建（status=PENDING），转账结果以转账通知为准。
func (r *DelayTransCreateTaskResponse) IsCreated() bool {
	return !r.IsError() && r.TaskStatus() == TaskStatusPending
}

// transResponse 是快捷支付和电子账户响应的公共部分。这些接口可能不带 status/data 外层，
// 直接返回 {"trans_status":"S",...}。
type transResponse struct{ Response }

func (r *transResponse) TransStatus() string { return r.String("trans_status") }
func (r *transResponse) RespDesc() string    { return r.String("resp_desc") }
func (r *transResponse) ErrMsg() string      { return r.String("err_msg") }

func (r *transResponse) IsError() bool {
	if r.hasEnvelope() {
		return r.Response.IsError()
	}
	s := r.TransStatus()
	return s == "" || s == TransStatusFailed
}

func (r *transResponse) ErrorInfo() string {
	if r.hasEnvelope() {
		return r.Response.ErrorInfo()
	}
	return fmt.Sprintf("[%s] %s", r.TransStatus(), firstNonEmpty(&r.Response, "resp_desc", "err_msg"))
}

func (r *transResponse) Err() error {
	if !r.IsError() {
		return nil
	}
	return &Error{Kind: ErrKindBiz, Message: r.ErrorInfo()}
}

func (r *transResponse) IsSuccessful() bool {
	return !r.IsError() && r.TransStatus() == TransStatusSucceeded
}

func (r *transResponse) IsProcessing() bool {
	return !r.IsError() && r.TransStatus() == TransStatusProcessing
}

// QuickPayBindApplyResponse 绑卡申请响应，成功后银行发送验证码。
type QuickPayBindApplyResponse struct{ transResponse }

func (r *QuickPayBindApplyResponse) TransID() string { return r.String("trans_id") }
func (r *QuickPayBindApplyResponse) OrderID() string { return r.String("order_id") }

// QuickPayBindConfirmResponse 绑卡确认响应。
type QuickPayBindConfirmResponse struct{ transResponse }

func (r *QuickPayBindConfirmResponse) ChannelUser() string   { return r.String("channel_user") }
func (r *QuickPayBindConfirmResponse) ChannelCardID() string { return r.String("channel_card_id") }

// HasBinding 为 true 时才保存 ChannelUser 与 ChannelCardID。
func (r *QuickPayBindConfirmResponse) HasBinding() bool {
	return r.IsSuccessful() && r.ChannelCardID() != ""
}

// QuickPayConfirmResponse 快捷支付确认响应。
type QuickPayConfirmResponse struct{ transResponse }

// QuickPaySmsCheckResponse 快捷支付短信校验响应。
type QuickPaySmsCheckResponse struct{ transResponse }

// QuickPayUnbindResponse 快捷支付解绑响应。
type QuickPayUnbindResponse struct{ transResponse }

func (r *QuickPayUnbindResponse) OrderID() string { return r.String("order_id") }

// accountResponse 是开户、业务开通、业务变更响应的公共部分，成功是 trans_status == "S"。
type accountResponse struct{ transResponse }

func (r *accountResponse) AccountNo() string { return r.String("account_no") }

// ElectronicAccountUserOpenResponse 开户响应，保存 AccountNo。
type ElectronicAccountUserOpenResponse struct{ accountResponse }

// ElectronicAccountBusinessOpenResponse 业务开通响应。
type ElectronicAccountBusinessOpenResponse struct{ accountResponse }

// ElectronicAccountBusinessChangeResponse 业务变更响应。
type ElectronicAccountBusinessChangeResponse struct{ accountResponse }

// ElectronicAccountUserQueryResponse 用户查询响应。trans_status 只表示查询是否成功。
type ElectronicAccountUserQueryResponse struct{ Response }

func (r *ElectronicAccountUserQueryResponse) TransStatus() string  { return r.String("trans_status") }
func (r *ElectronicAccountUserQueryResponse) AccountNo() string    { return r.String("account_no") }
func (r *ElectronicAccountUserQueryResponse) AccountName() string  { return r.String("account_name") }
func (r *ElectronicAccountUserQueryResponse) AccountType() string  { return r.String("account_type") }
func (r *ElectronicAccountUserQueryResponse) RelationType() string { return r.String("relation_type") }
func (r *ElectronicAccountUserQueryResponse) DivisionProfit() string {
	return r.String("division_profit")
}
func (r *ElectronicAccountUserQueryResponse) RespDesc() string { return r.String("resp_desc") }

// ChannelStatus 是资料里的渠道入驻状态：0 待入驻、1 成功、2 失败。
func (r *ElectronicAccountUserQueryResponse) ChannelStatus() string {
	return r.String("channel_status")
}

func (r *ElectronicAccountUserQueryResponse) RelationTypeName() string {
	return r.String("relation_type_name")
}

// ChannelExtra 返回查到的 channel_extra。查询结果里的字段只用于展示，不能原样提交到业务开通或变更。
func (r *ElectronicAccountUserQueryResponse) ChannelExtra() map[string]interface{} {
	switch v := r.Value("channel_extra").(type) {
	case map[string]interface{}:
		return v
	case string:
		if m, err := decodeJSONObject([]byte(v)); err == nil && m != nil {
			return m
		}
	}
	return map[string]interface{}{}
}

func (r *ElectronicAccountUserQueryResponse) IsQuerySuccessful() bool {
	return !r.IsError() && r.TransStatus() == TransStatusSucceeded
}

// IsOnboarded 只表示 channel_status 为 1，不能用来判断开户是否成功。
func (r *ElectronicAccountUserQueryResponse) IsOnboarded() bool {
	return r.IsQuerySuccessful() && r.ChannelStatus() == "1"
}

// ElectronicAccountSettlementQueryResponse 结算记录查询响应。金额为元字符串。
type ElectronicAccountSettlementQueryResponse struct{ Response }

// Records 返回结算记录。data 可能是单条记录、记录数组或带 records/list/rows 的对象。
func (r *ElectronicAccountSettlementQueryResponse) Records() []map[string]interface{} {
	if list, ok := r.rawData().([]interface{}); ok {
		return objectList(list)
	}
	data := r.Data()
	for _, key := range []string{"records", "list", "rows"} {
		if list, ok := data[key].([]interface{}); ok {
			return objectList(list)
		}
	}
	if _, ok := data["trans_id"]; ok {
		return []map[string]interface{}{data}
	}
	return nil
}

func (r *ElectronicAccountSettlementQueryResponse) record(key string) string {
	return firstRecordValue(r.Records(), key)
}

func (r *ElectronicAccountSettlementQueryResponse) TransID() string   { return r.record("trans_id") }
func (r *ElectronicAccountSettlementQueryResponse) TransDate() string { return r.record("trans_date") }
func (r *ElectronicAccountSettlementQueryResponse) SettleCycle() string {
	return r.record("settle_cycle")
}
func (r *ElectronicAccountSettlementQueryResponse) TransAmount() string { return r.record("trans_amt") }
func (r *ElectronicAccountSettlementQueryResponse) FeeAmount() string   { return r.record("fee_amt") }

// TransStat 是第一条记录的结算状态：I 处理中、F 失败、S 成功。
func (r *ElectronicAccountSettlementQueryResponse) TransStat() string { return r.record("trans_stat") }

// ElectronicAccountBalanceWithdrawResponse 余额提现响应。
type ElectronicAccountBalanceWithdrawResponse struct{ Response }

// AcceptanceStatus 是 data.status，success 或 succeess 都表示已受理（succeess 是平台定义的值）。
func (r *ElectronicAccountBalanceWithdrawResponse) AcceptanceStatus() string {
	return objectString(r.Data(), "status")
}
func (r *ElectronicAccountBalanceWithdrawResponse) AcceptanceMessage() string {
	return r.String("err_msg")
}
func (r *ElectronicAccountBalanceWithdrawResponse) IsAccepted() bool {
	return !r.IsError() && isChannelSuccess(r.AcceptanceStatus())
}

// ElectronicAccountSingleWithdrawQueryResponse 单笔提现查询响应。金额为元字符串。
type ElectronicAccountSingleWithdrawQueryResponse struct{ Response }

func (r *ElectronicAccountSingleWithdrawQueryResponse) WithdrawDate() string {
	return r.String("withdraw_date")
}
func (r *ElectronicAccountSingleWithdrawQueryResponse) OrderID() string { return r.String("order_id") }
func (r *ElectronicAccountSingleWithdrawQueryResponse) CashAmount() string {
	return r.String("cash_amt")
}
func (r *ElectronicAccountSingleWithdrawQueryResponse) FeeAmount() string { return r.String("fee_amt") }
func (r *ElectronicAccountSingleWithdrawQueryResponse) TransDesc() string {
	return r.String("trans_desc")
}

// TransStatus 是提现状态：pending、failed、succeeded。
func (r *ElectronicAccountSingleWithdrawQueryResponse) TransStatus() string {
	return r.String("trans_status")
}
func (r *ElectronicAccountSingleWithdrawQueryResponse) ChannelStatus() string {
	return objectString(r.Data(), "status")
}
func (r *ElectronicAccountSingleWithdrawQueryResponse) IsChannelSuccessful() bool {
	return isChannelSuccess(r.ChannelStatus())
}

// ElectronicAccountBatchWithdrawQueryResponse 批量提现查询响应。分页信息在 data，记录列表在 data.data。
type ElectronicAccountBatchWithdrawQueryResponse struct{ Response }

func (r *ElectronicAccountBatchWithdrawQueryResponse) Records() []map[string]interface{} {
	return objectList(r.Data()["data"])
}
func (r *ElectronicAccountBatchWithdrawQueryResponse) Count() int64 { return r.intOrZero("count") }
func (r *ElectronicAccountBatchWithdrawQueryResponse) PageSize() int64 {
	return r.intOrZero("page_size")
}
func (r *ElectronicAccountBatchWithdrawQueryResponse) PageNum() int64 { return r.intOrZero("page_num") }
func (r *ElectronicAccountBatchWithdrawQueryResponse) ChannelStatus() string {
	return objectString(r.Data(), "status")
}
func (r *ElectronicAccountBatchWithdrawQueryResponse) IsChannelSuccessful() bool {
	return isChannelSuccess(r.ChannelStatus())
}

// BalanceQueryResponse 余额查询响应。金额为元字符串，可能不带 status/data 外层。
type BalanceQueryResponse struct{ Response }

func (r *BalanceQueryResponse) IsError() bool {
	if _, ok := r.body["status"]; !ok {
		if _, flat := r.body["acct_type"]; flat {
			return false
		}
	}
	return r.Response.IsError()
}

func (r *BalanceQueryResponse) Err() error {
	if !r.IsError() {
		return nil
	}
	return &Error{Kind: ErrKindBiz, Message: r.ErrorInfo()}
}

func (r *BalanceQueryResponse) AccountType() string { return r.String("acct_type") }

// AccountStatus：N 正常、C 关闭、F 冻结、D 销户。
func (r *BalanceQueryResponse) AccountStatus() string     { return r.String("acct_stat") }
func (r *BalanceQueryResponse) BalanceAmount() string     { return r.String("balance_amt") }
func (r *BalanceQueryResponse) AvailableBalance() string  { return r.String("avl_balance") }
func (r *BalanceQueryResponse) FrozenBalance() string     { return r.String("frz_bal") }
func (r *BalanceQueryResponse) HistoricalBalance() string { return r.String("dm_amt") }
func (r *BalanceQueryResponse) IsNormal() bool            { return !r.IsError() && r.AccountStatus() == "N" }

func isChannelSuccess(status string) bool {
	s := strings.ToLower(status)
	return s == "success" || s == "succeess"
}

func firstRecordValue(records []map[string]interface{}, key string) string {
	if len(records) == 0 {
		return ""
	}
	return objectString(records[0], key)
}
