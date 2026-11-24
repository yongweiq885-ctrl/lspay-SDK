package lspay

import "time"

// ElectronicAccountUserOpenRequest 电子账户开户 gateway/user/personapply，way_code 固定 APPLY。
// 成功看响应 trans_status == "S"，保存返回的 account_no。
type ElectronicAccountUserOpenRequest struct {
	OrderID          string
	AccountName      string // 个人填姓名，企业和个体户填企业名称
	AccountType      int    // AccountTypePersonal、AccountTypeEnterprise、AccountTypeIndividualBusiness
	RelationType     string // RelationType* 常量
	RelationTypeName string // RelationType 为 CUSTOM 时必填
	AccountAlias     string
	// DivisionProfit 是分账比例字符串，"0.0410" 表示 4.10%；只做转账或余额支付时填 "0"。
	DivisionProfit string
	ClientIP       string
	// ChannelExtra 接受 *ElectronicAccountOpenChannelExtra、map 或 JSON 字符串。
	ChannelExtra interface{}
	ExtendInfo   map[string]string
}

func (r *ElectronicAccountUserOpenRequest) Endpoint() string {
	return EndpointElectronicAccountUserOpen
}
func (r *ElectronicAccountUserOpenRequest) Validate() error { return validate(r) }

func (r *ElectronicAccountUserOpenRequest) Params() (map[string]string, error) {
	if err := requiredString("order_id", r.OrderID, 30); err != nil {
		return nil, err
	}
	if err := requiredString("account_name", r.AccountName, 30); err != nil {
		return nil, err
	}
	if r.AccountType < AccountTypePersonal || r.AccountType > AccountTypeIndividualBusiness {
		return nil, validationError("account_type 只允许 1、2、3")
	}
	if err := requiredString("relation_type", r.RelationType, 30); err != nil {
		return nil, err
	}
	if !relationTypes[r.RelationType] {
		return nil, validationError("relation_type 非法")
	}
	if r.RelationType == RelationTypeCustom {
		if err := requiredString("relation_type_name", r.RelationTypeName, 30); err != nil {
			return nil, err
		}
	}
	if err := requiredString("account_alias", r.AccountAlias, 30); err != nil {
		return nil, err
	}
	if !divisionProfitPattern.MatchString(r.DivisionProfit) {
		return nil, validationError("division_profit 必须是 0 到 1 之间的十进制比例")
	}
	if err := checkIPv4("client_ip", r.ClientIP); err != nil {
		return nil, err
	}
	_, extra, err := normalizeJSONObject("channel_extra", r.ChannelExtra)
	if err != nil {
		return nil, err
	}
	if r.AccountType == AccountTypePersonal {
		info, ok := extra["user_info"].(map[string]interface{})
		if !ok {
			return nil, validationError("account_type=1 时 channel_extra.user_info 必填")
		}
		if err := validateUserInfo(info); err != nil {
			return nil, err
		}
	} else {
		info, ok := extra["mer_info"].(map[string]interface{})
		if !ok {
			return nil, validationError("account_type=2 或 3 时 channel_extra.mer_info 必填")
		}
		if err := validateMerchantInfo(info); err != nil {
			return nil, err
		}
	}
	extraJSON, err := reencode("channel_extra", extra)
	if err != nil {
		return nil, err
	}
	if err := checkJSONLength("channel_extra", extraJSON, 2048); err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeApply}
	p.set("order_id", r.OrderID)
	p.set("account_name", r.AccountName)
	p.setInt("account_type", int64(r.AccountType))
	p.set("relation_type", r.RelationType)
	p.set("relation_type_name", r.RelationTypeName)
	p.set("account_alias", r.AccountAlias)
	p.set("division_profit", r.DivisionProfit)
	p.set("client_ip", r.ClientIP)
	p.set("channel_extra", extraJSON)
	return p.finish(r.ExtendInfo)
}

// ElectronicAccountBusinessOpenRequest 电子账户业务开通 gateway/user/personconfirm，way_code 固定 APPLY。
type ElectronicAccountBusinessOpenRequest struct {
	OrderID   string
	AccountNo string // 开户返回的用户编号
	ClientIP  string
	// ChannelExtra 接受 *ElectronicAccountBusinessChannelExtra、map 或 JSON 字符串。
	ChannelExtra interface{}
	ExtendInfo   map[string]string
}

func (r *ElectronicAccountBusinessOpenRequest) Endpoint() string {
	return EndpointElectronicAccountBusinessOpen
}
func (r *ElectronicAccountBusinessOpenRequest) Validate() error { return validate(r) }

func (r *ElectronicAccountBusinessOpenRequest) Params() (map[string]string, error) {
	return businessParams(r.OrderID, r.AccountNo, r.ClientIP, r.ChannelExtra, false, r.ExtendInfo)
}

// ElectronicAccountBusinessChangeRequest 电子账户业务变更 gateway/user/personmodify，way_code 固定 APPLY。
// card_info 只有 card_type、card_name、card_no 三项。
type ElectronicAccountBusinessChangeRequest struct {
	OrderID   string
	AccountNo string
	ClientIP  string
	// ChannelExtra 接受 *ElectronicAccountBusinessChangeChannelExtra、map 或 JSON 字符串。
	ChannelExtra interface{}
	ExtendInfo   map[string]string
}

func (r *ElectronicAccountBusinessChangeRequest) Endpoint() string {
	return EndpointElectronicAccountBusinessChange
}
func (r *ElectronicAccountBusinessChangeRequest) Validate() error { return validate(r) }

func (r *ElectronicAccountBusinessChangeRequest) Params() (map[string]string, error) {
	return businessParams(r.OrderID, r.AccountNo, r.ClientIP, r.ChannelExtra, true, r.ExtendInfo)
}

func businessParams(orderID, accountNo, clientIP string, channelExtra interface{}, change bool, extend map[string]string) (map[string]string, error) {
	if err := requiredString("order_id", orderID, 30); err != nil {
		return nil, err
	}
	if err := requiredString("account_no", accountNo, 32); err != nil {
		return nil, err
	}
	if err := checkIPv4("client_ip", clientIP); err != nil {
		return nil, err
	}
	_, extra, err := normalizeJSONObject("channel_extra", channelExtra)
	if err != nil {
		return nil, err
	}
	if err := validateBusinessExtra(extra, change); err != nil {
		return nil, err
	}
	extraJSON, err := reencode("channel_extra", extra)
	if err != nil {
		return nil, err
	}
	if err := checkJSONLength("channel_extra", extraJSON, 2048); err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeApply}
	p.set("order_id", orderID)
	p.set("account_no", accountNo)
	p.set("client_ip", clientIP)
	p.set("channel_extra", extraJSON)
	return p.finish(extend)
}

// ElectronicAccountUserQueryRequest 电子账户用户查询 gateway/user/query，way_code 固定 APPLY。
// 响应的 trans_status 只表示查询是否成功，不能用来判断开户结果。
type ElectronicAccountUserQueryRequest struct {
	AccountNo  string
	ClientIP   string
	ExtendInfo map[string]string
}

func (r *ElectronicAccountUserQueryRequest) Endpoint() string {
	return EndpointElectronicAccountUserQuery
}
func (r *ElectronicAccountUserQueryRequest) Validate() error { return validate(r) }

func (r *ElectronicAccountUserQueryRequest) Params() (map[string]string, error) {
	if err := requiredString("account_no", r.AccountNo, 32); err != nil {
		return nil, err
	}
	if err := checkIPv4("client_ip", r.ClientIP); err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeApply}
	p.set("account_no", r.AccountNo)
	p.set("client_ip", r.ClientIP)
	return p.finish(r.ExtendInfo)
}

// ElectronicAccountSettlementQueryRequest 结算记录查询 gateway/user/settlement/query，日期跨度不超过 30 天。
type ElectronicAccountSettlementQueryRequest struct {
	OrderID    string
	AccountNo  string
	BeginDate  string // yyyyMMdd
	EndDate    string // yyyyMMdd
	PageSize   int
	PageNum    int
	ExtendInfo map[string]string
}

func (r *ElectronicAccountSettlementQueryRequest) Endpoint() string {
	return EndpointElectronicAccountSettlementQuery
}
func (r *ElectronicAccountSettlementQueryRequest) Validate() error { return validate(r) }

func (r *ElectronicAccountSettlementQueryRequest) Params() (map[string]string, error) {
	if err := requiredString("order_id", r.OrderID, 30); err != nil {
		return nil, err
	}
	if err := requiredString("account_no", r.AccountNo, 32); err != nil {
		return nil, err
	}
	begin, end, err := dateRange(r.BeginDate, r.EndDate)
	if err != nil {
		return nil, err
	}
	if end.Sub(begin) > 30*24*time.Hour {
		return nil, validationError("结算查询日期跨度不能超过 30 天")
	}
	if err := checkPaging(r.PageSize, r.PageNum); err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeSettleQuery}
	p.set("order_id", r.OrderID)
	p.set("account_no", r.AccountNo)
	p.set("begin_date", r.BeginDate)
	p.set("end_date", r.EndDate)
	p.setInt("page_size", int64(r.PageSize))
	p.setInt("page_num", int64(r.PageNum))
	return p.finish(r.ExtendInfo)
}

// ElectronicAccountBalanceWithdrawRequest 余额提现 gateway/user/balance/withdraw。
type ElectronicAccountBalanceWithdrawRequest struct {
	OrderID   string // 唯一，单笔提现查询要用
	AccountNo string
	// WithdrawAmount 单位是元，字符串，最多 2 位小数，如 "99.99"。
	WithdrawAmount string
	ExtendInfo     map[string]string
}

func (r *ElectronicAccountBalanceWithdrawRequest) Endpoint() string {
	return EndpointElectronicAccountBalanceWithdraw
}
func (r *ElectronicAccountBalanceWithdrawRequest) Validate() error { return validate(r) }

func (r *ElectronicAccountBalanceWithdrawRequest) Params() (map[string]string, error) {
	if err := requiredString("order_id", r.OrderID, 30); err != nil {
		return nil, err
	}
	if err := requiredString("account_no", r.AccountNo, 32); err != nil {
		return nil, err
	}
	amount := r.WithdrawAmount
	if len(amount) > 32 || !withdrawAmountPattern.MatchString(amount) {
		return nil, validationError("withdraw_amount 必须是大于 0 且最多保留 2 位小数的元金额")
	}
	if fen, _ := YuanToFen(amount); fen <= 0 {
		return nil, validationError("withdraw_amount 必须是大于 0 且最多保留 2 位小数的元金额")
	}
	p := params{"way_code": WayCodeBalanceWithdraw}
	p.set("order_id", r.OrderID)
	p.set("account_no", r.AccountNo)
	p.set("withdraw_amount", amount)
	return p.finish(r.ExtendInfo)
}

// ElectronicAccountSingleWithdrawQueryRequest 单笔提现查询 gateway/user/balance/withdraw/single/query。
type ElectronicAccountSingleWithdrawQueryRequest struct {
	AccountNo    string
	WithdrawDate string // yyyyMMdd
	OrderID      string // 原提现流水号
	ExtendInfo   map[string]string
}

func (r *ElectronicAccountSingleWithdrawQueryRequest) Endpoint() string {
	return EndpointElectronicAccountSingleWithdrawQuery
}
func (r *ElectronicAccountSingleWithdrawQueryRequest) Validate() error { return validate(r) }

func (r *ElectronicAccountSingleWithdrawQueryRequest) Params() (map[string]string, error) {
	if err := requiredString("account_no", r.AccountNo, 32); err != nil {
		return nil, err
	}
	if _, err := parseDate8("withdraw_date", r.WithdrawDate); err != nil {
		return nil, err
	}
	if err := requiredString("order_id", r.OrderID, 30); err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeWithdrawSingleQuery}
	p.set("account_no", r.AccountNo)
	p.set("withdraw_date", r.WithdrawDate)
	p.set("order_id", r.OrderID)
	return p.finish(r.ExtendInfo)
}

// ElectronicAccountBatchWithdrawQueryRequest 批量提现查询 gateway/user/balance/withdraw/query。
// 文档要求日期跨度小于 3 天，SDK 不做限制。
type ElectronicAccountBatchWithdrawQueryRequest struct {
	AccountNo  string
	BeginDate  string // yyyyMMdd
	EndDate    string // yyyyMMdd
	PageSize   int
	PageNum    int
	ExtendInfo map[string]string
}

func (r *ElectronicAccountBatchWithdrawQueryRequest) Endpoint() string {
	return EndpointElectronicAccountBatchWithdrawQuery
}
func (r *ElectronicAccountBatchWithdrawQueryRequest) Validate() error { return validate(r) }

func (r *ElectronicAccountBatchWithdrawQueryRequest) Params() (map[string]string, error) {
	if err := requiredString("account_no", r.AccountNo, 32); err != nil {
		return nil, err
	}
	if _, _, err := dateRange(r.BeginDate, r.EndDate); err != nil {
		return nil, err
	}
	if err := checkPaging(r.PageSize, r.PageNum); err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeWithdrawQuery}
	p.set("account_no", r.AccountNo)
	p.set("begin_date", r.BeginDate)
	p.set("end_date", r.EndDate)
	p.setInt("page_size", int64(r.PageSize))
	p.setInt("page_num", int64(r.PageNum))
	return p.finish(r.ExtendInfo)
}

// BalanceQueryRequest 余额查询 gateway/user/balance/query。
type BalanceQueryRequest struct {
	OrderID    string
	AccountNo  string
	ClientIP   string
	ExtendInfo map[string]string
}

func (r *BalanceQueryRequest) Endpoint() string { return EndpointBalanceQuery }
func (r *BalanceQueryRequest) Validate() error  { return validate(r) }

func (r *BalanceQueryRequest) Params() (map[string]string, error) {
	if err := requiredString("order_id", r.OrderID, 30); err != nil {
		return nil, err
	}
	if err := requiredString("account_no", r.AccountNo, 32); err != nil {
		return nil, err
	}
	if err := checkIPv4("client_ip", r.ClientIP); err != nil {
		return nil, err
	}
	p := params{"way_code": WayCodeBalanceQuery}
	p.set("order_id", r.OrderID)
	p.set("account_no", r.AccountNo)
	p.set("client_ip", r.ClientIP)
	return p.finish(r.ExtendInfo)
}

func dateRange(beginText, endText string) (time.Time, time.Time, error) {
	begin, err := parseDate8("begin_date", beginText)
	if err != nil {
		return begin, begin, err
	}
	end, err := parseDate8("end_date", endText)
	if err != nil {
		return begin, end, err
	}
	if begin.After(end) {
		return begin, end, validationError("begin_date 不能晚于 end_date")
	}
	return begin, end, nil
}

func checkPaging(pageSize, pageNum int) error {
	if pageSize < 0 || pageNum < 0 {
		return validationError("page_size 与 page_num 必须是正整数")
	}
	return nil
}
