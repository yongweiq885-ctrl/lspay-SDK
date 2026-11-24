package lspay

import "fmt"

// 以下结构体用于构造 channel_extra。请求的 ChannelExtra 字段也接受 map[string]interface{}
// 或 JSON 对象字符串；三种写法走同一套校验，并在发送前统一序列化为紧凑 JSON。

// DivisionReceiver 是 div_list 中的一个分账接收方。
type DivisionReceiver struct {
	// TransIn 是接收方电子账户编号（开户返回的 account_no）。
	TransIn string `json:"trans_in"`
	// DivAmt 是分账金额（分）。为 0 时不传，按开户时的 division_profit 分账。
	DivAmt      int64  `json:"div_amt,omitempty"`
	Description string `json:"description,omitempty"`
}

// UnifiedOrderChannelExtra 用于统一下单和快捷支付申请。
type UnifiedOrderChannelExtra struct {
	PayDataType   string             `json:"pay_data_type,omitempty"`
	OpenID        string             `json:"openid,omitempty"`
	BuyerID       string             `json:"buyer_id,omitempty"`
	AuthCode      string             `json:"auth_code,omitempty"`
	ChannelUser   string             `json:"channel_user,omitempty"`
	ChannelCardID string             `json:"channel_card_id,omitempty"`
	AppSchema     string             `json:"app_schema,omitempty"`
	DivList       []DivisionReceiver `json:"div_list,omitempty"`
}

// DivisionChannelExtra 用于延时分账交易确认和已分账订单退款。
type DivisionChannelExtra struct {
	DivList []DivisionReceiver `json:"div_list,omitempty"`
}

// BalancePayChannelExtra 用于余额支付，每个接收方的 DivAmt 必填。
type BalancePayChannelExtra struct {
	TransOut string             `json:"trans_out"`
	DivList  []DivisionReceiver `json:"div_list"`
}

// QuickPayBindChannelExtra 是快捷支付绑卡申请的四要素。CertType 为空时按 "00"（身份证）发送。
type QuickPayBindChannelExtra struct {
	MchUserID        string `json:"mch_user_id"`
	CertType         string `json:"cert_type,omitempty"`
	CardID           string `json:"card_id"`
	CardName         string `json:"card_name"`
	CertID           string `json:"cert_id"`
	CertValidityType string `json:"cert_validity_type"`
	CertBeginDate    string `json:"cert_begin_date"`
	CertEndDate      string `json:"cert_end_date,omitempty"`
	CardMp           string `json:"card_mp"`
	DcType           string `json:"dc_type"`
	VipCode          string `json:"vip_code,omitempty"`
	Expiration       string `json:"expiration,omitempty"`
}

// QuickPayBindConfirmChannelExtra 必须与绑卡申请时一致。
type QuickPayBindConfirmChannelExtra struct {
	MchUserID string `json:"mch_user_id"`
	CertType  string `json:"cert_type,omitempty"`
	CardID    string `json:"card_id"`
	CardName  string `json:"card_name"`
	CertID    string `json:"cert_id"`
	CardMp    string `json:"card_mp"`
	DcType    string `json:"dc_type"`
}

// QuickPayUnbindChannelExtra 用于快捷支付解绑。
type QuickPayUnbindChannelExtra struct {
	MchUserID     string `json:"mch_user_id"`
	ChannelCardID string `json:"channel_card_id"`
}

// ElectronicAccountOpenChannelExtra 个人传 UserInfo，企业和个体户传 MerInfo。
type ElectronicAccountOpenChannelExtra struct {
	UserInfo *ElectronicAccountUserInfo     `json:"user_info,omitempty"`
	MerInfo  *ElectronicAccountMerchantInfo `json:"mer_info,omitempty"`
}

// ElectronicAccountUserInfo 是个人开户资料。CertType 为空时按 "00" 发送。
type ElectronicAccountUserInfo struct {
	CertType         string `json:"cert_type,omitempty"`
	Name             string `json:"name"`
	CertNo           string `json:"cert_no"`
	CertValidityType string `json:"cert_validity_type"`
	CertBeginDate    string `json:"cert_begin_date"`
	CertEndDate      string `json:"cert_end_date,omitempty"`
	MobileNo         string `json:"mobile_no"`
	Email            string `json:"email"`
}

// ElectronicAccountMerchantInfo 是企业或个体户开户资料。LegalCertType 为空时按 "00" 发送。
type ElectronicAccountMerchantInfo struct {
	RegName               string `json:"reg_name"`
	ShortName             string `json:"short_name"`
	LicenseCode           string `json:"license_code"`
	LicenseValidityType   string `json:"license_validity_type"`
	LicenseBeginDate      string `json:"license_begin_date"`
	LicenseEndDate        string `json:"license_end_date,omitempty"`
	RegProvID             string `json:"reg_prov_id"`
	RegAreaID             string `json:"reg_area_id"`
	RegDistrictID         string `json:"reg_district_id"`
	RegDetail             string `json:"reg_detail"`
	LegalName             string `json:"legal_name"`
	LegalCertType         string `json:"legal_cert_type,omitempty"`
	LegalCertNo           string `json:"legal_cert_no"`
	LegalCertValidityType string `json:"legal_cert_validity_type"`
	LegalCertBeginDate    string `json:"legal_cert_begin_date"`
	LegalCertEndDate      string `json:"legal_cert_end_date,omitempty"`
	ContactName           string `json:"contact_name"`
	ContactMobile         string `json:"contact_mobile"`
}

// ElectronicAccountSettlementCard 是业务开通的结算卡。
// CardType：0 对公（需 BankCode，BranchCode 与 BranchName 二选一），1 对私，2 对私非法人（对私需持卡人证件）。
type ElectronicAccountSettlementCard struct {
	CardType         string `json:"card_type"`
	CardName         string `json:"card_name"`
	CardNo           string `json:"card_no"`
	ProvID           string `json:"prov_id"`
	AreaID           string `json:"area_id"`
	BankCode         string `json:"bank_code,omitempty"`
	BranchCode       string `json:"branch_code,omitempty"`
	BranchName       string `json:"branch_name,omitempty"`
	CertType         string `json:"cert_type,omitempty"`
	CertNo           string `json:"cert_no,omitempty"`
	CertValidityType string `json:"cert_validity_type,omitempty"`
	CertBeginDate    string `json:"cert_begin_date,omitempty"`
	CertEndDate      string `json:"cert_end_date,omitempty"`
	Mp               string `json:"mp"`
}

// ElectronicAccountModifiedCard 是业务变更的结算卡，只有三项。
type ElectronicAccountModifiedCard struct {
	CardType string `json:"card_type"`
	CardName string `json:"card_name"`
	CardNo   string `json:"card_no"`
}

// ElectronicAccountSettleConfig：SettleCycle 为 D1（自然日）或 T1（工作日），SettleStatus 1 开启、2 关闭。
type ElectronicAccountSettleConfig struct {
	SettleCycle  string `json:"settle_cycle"`
	SettleStatus int    `json:"settle_status"`
}

// ElectronicAccountCashConfig：CashType 为 D0、D1、T1、DM；SwitchState 1 开启、2 关闭；OutFeeFlag 1 商户承担、2 用户承担。
type ElectronicAccountCashConfig struct {
	CashType    string `json:"cash_type"`
	SwitchState int    `json:"switch_state"`
	OutFeeFlag  int    `json:"out_fee_flag"`
}

// ElectronicAccountBusinessChannelExtra 用于电子账户业务开通。
type ElectronicAccountBusinessChannelExtra struct {
	CardInfo     *ElectronicAccountSettlementCard `json:"card_info"`
	SettleConfig *ElectronicAccountSettleConfig   `json:"settle_config"`
	CashConfig   []ElectronicAccountCashConfig    `json:"cash_config"`
}

// ElectronicAccountBusinessChangeChannelExtra 用于电子账户业务变更。
type ElectronicAccountBusinessChangeChannelExtra struct {
	CardInfo     *ElectronicAccountModifiedCard `json:"card_info"`
	SettleConfig *ElectronicAccountSettleConfig `json:"settle_config"`
	CashConfig   []ElectronicAccountCashConfig  `json:"cash_config"`
}

func validateDivisionList(v interface{}, required bool) error {
	list, ok := v.([]interface{})
	if !ok || (required && len(list) == 0) {
		if required {
			return validationError("channel_extra.div_list 必须是非空数组")
		}
		return validationError("channel_extra.div_list 必须是数组")
	}
	for i, item := range list {
		prefix := fmt.Sprintf("channel_extra.div_list[%d]", i)
		obj, ok := item.(map[string]interface{})
		if !ok {
			return validationError("%s 必须是 JSON 对象", prefix)
		}
		if _, err := requiredField(obj, "trans_in", prefix+".trans_in", 256); err != nil {
			return err
		}
		if amount, exists := obj["div_amt"]; exists {
			if n, ok := strictInt(amount); !ok || n <= 0 {
				return validationError("%s.div_amt 必须是大于 0 的整数（单位：分）", prefix)
			}
		}
		if desc, exists := obj["description"]; exists {
			if _, ok := desc.(string); !ok {
				return validationError("%s.description 必须是字符串", prefix)
			}
		}
	}
	return nil
}

func setDefault(m map[string]interface{}, key, value string) {
	if s, _ := scalarString(m[key]); s == "" {
		m[key] = value
	}
}

func checkValidityDates(m map[string]interface{}, prefix, path string) error {
	kind := objectString(m, prefix+"_validity_type")
	if kind != "0" && kind != "1" {
		return validationError("%s.%s_validity_type 只允许 0（非长期）或 1（长期）", path, prefix)
	}
	begin, err := parseDate8(path+"."+prefix+"_begin_date", objectString(m, prefix+"_begin_date"))
	if err != nil {
		return err
	}
	endText := objectString(m, prefix+"_end_date")
	if kind == "0" && endText == "" {
		return validationError("非长期有效证照必须填写 %s.%s_end_date", path, prefix)
	}
	if endText != "" {
		end, err := parseDate8(path+"."+prefix+"_end_date", endText)
		if err != nil {
			return err
		}
		if end.Before(begin) {
			return validationError("%s.%s_end_date 不能早于开始日期", path, prefix)
		}
	}
	return nil
}

func checkDcType(m map[string]interface{}) error {
	switch objectString(m, "dc_type") {
	case "C", "D", "S", "U":
		return nil
	}
	return validationError("channel_extra.dc_type 只允许 C、D、S、U")
}

func validateQuickPayBind(extra map[string]interface{}) error {
	if extra == nil {
		return validationError("channel_extra 必填")
	}
	setDefault(extra, "cert_type", "00")
	if objectString(extra, "cert_type") != "00" {
		return validationError("channel_extra.cert_type 仅支持 00（身份证）")
	}
	if err := requiredFields(extra, "channel_extra", "mch_user_id", "card_id", "card_name", "cert_id",
		"cert_validity_type", "cert_begin_date", "card_mp", "dc_type"); err != nil {
		return err
	}
	if err := checkValidityDates(extra, "cert", "channel_extra"); err != nil {
		return err
	}
	if err := checkDcType(extra); err != nil {
		return err
	}
	if exp := objectString(extra, "expiration"); exp != "" && !expirationPattern.MatchString(exp) {
		return validationError("channel_extra.expiration 必须是 MMYY 格式")
	}
	return nil
}

func validateQuickPayBindConfirm(extra map[string]interface{}) error {
	if extra == nil {
		return validationError("channel_extra 必填")
	}
	setDefault(extra, "cert_type", "00")
	if err := requiredFields(extra, "channel_extra", "mch_user_id", "card_id", "card_name", "cert_id",
		"card_mp", "dc_type"); err != nil {
		return err
	}
	return checkDcType(extra)
}

func validateUserInfo(m map[string]interface{}) error {
	const path = "channel_extra.user_info"
	setDefault(m, "cert_type", "00")
	if err := requiredFields(m, path, "name", "cert_no", "cert_validity_type", "cert_begin_date",
		"mobile_no", "email"); err != nil {
		return err
	}
	if err := checkValidityDates(m, "cert", path); err != nil {
		return err
	}
	if !emailPattern.MatchString(objectString(m, "email")) {
		return validationError("%s.email 必须是合法邮箱", path)
	}
	return nil
}

func validateMerchantInfo(m map[string]interface{}) error {
	const path = "channel_extra.mer_info"
	setDefault(m, "legal_cert_type", "00")
	if err := requiredFields(m, path, "reg_name", "short_name", "license_code", "license_validity_type",
		"license_begin_date", "reg_prov_id", "reg_area_id", "reg_district_id", "reg_detail",
		"legal_name", "legal_cert_type", "legal_cert_no", "legal_cert_validity_type",
		"legal_cert_begin_date", "contact_name", "contact_mobile"); err != nil {
		return err
	}
	if err := checkValidityDates(m, "license", path); err != nil {
		return err
	}
	if err := checkValidityDates(m, "legal_cert", path); err != nil {
		return err
	}
	for _, key := range []string{"reg_prov_id", "reg_area_id", "reg_district_id"} {
		if !region6Pattern.MatchString(objectString(m, key)) {
			return validationError("%s.%s 必须是 6 位地区编码", path, key)
		}
	}
	return nil
}

func validateSettlementCard(m map[string]interface{}) error {
	const path = "channel_extra.card_info"
	if err := requiredFields(m, path, "card_type", "card_name", "card_no", "prov_id", "area_id", "mp"); err != nil {
		return err
	}
	cardType := objectString(m, "card_type")
	if cardType != "0" && cardType != "1" && cardType != "2" {
		return validationError("%s.card_type 只允许 0、1、2", path)
	}
	for _, key := range []string{"prov_id", "area_id"} {
		if !region6Pattern.MatchString(objectString(m, key)) {
			return validationError("%s.%s 必须是 6 位地区编码", path, key)
		}
	}
	if cardType == "0" {
		if err := requiredFields(m, path, "bank_code"); err != nil {
			return err
		}
		if objectString(m, "branch_code") == "" && objectString(m, "branch_name") == "" {
			return validationError("对公结算卡的 branch_code 与 branch_name 至少填写一个")
		}
		return nil
	}
	setDefault(m, "cert_type", "00")
	if err := requiredFields(m, path, "cert_no", "cert_validity_type", "cert_begin_date"); err != nil {
		return err
	}
	return checkValidityDates(m, "cert", path)
}

func validateModifiedCard(m map[string]interface{}) error {
	const path = "channel_extra.card_info"
	cardType := objectString(m, "card_type")
	if cardType != "0" && cardType != "1" && cardType != "2" {
		return validationError("%s.card_type 只允许 0、1、2", path)
	}
	return requiredFields(m, path, "card_name", "card_no")
}

func validateBusinessExtra(extra map[string]interface{}, change bool) error {
	card, cardOK := extra["card_info"].(map[string]interface{})
	settle, settleOK := extra["settle_config"].(map[string]interface{})
	if !cardOK || !settleOK {
		return validationError("channel_extra.card_info 与 settle_config 必填")
	}
	cash, _ := extra["cash_config"].([]interface{})
	if len(cash) == 0 {
		return validationError("channel_extra.cash_config 必须是非空数组")
	}
	var err error
	if change {
		err = validateModifiedCard(card)
	} else {
		err = validateSettlementCard(card)
	}
	if err != nil {
		return err
	}
	cycle := objectString(settle, "settle_cycle")
	if cycle != "D1" && cycle != "T1" {
		return validationError("channel_extra.settle_config.settle_cycle 只允许 D1 或 T1")
	}
	if n, ok := looseInt(settle["settle_status"]); !ok || (n != 1 && n != 2) {
		return validationError("channel_extra.settle_config.settle_status 只允许 1 或 2")
	}
	for i, item := range cash {
		prefix := fmt.Sprintf("channel_extra.cash_config[%d]", i)
		obj, ok := item.(map[string]interface{})
		if !ok {
			return validationError("%s 必须是对象", prefix)
		}
		switch objectString(obj, "cash_type") {
		case "D0", "D1", "T1", "DM":
		default:
			return validationError("%s.cash_type 只允许 D0、D1、T1、DM", prefix)
		}
		for _, key := range []string{"switch_state", "out_fee_flag"} {
			if n, ok := looseInt(obj[key]); !ok || (n != 1 && n != 2) {
				return validationError("%s.%s 只允许 1 或 2", prefix, key)
			}
		}
	}
	return nil
}

// reencode 在校验过程中补了默认值后，重新生成发送用的 JSON。
func reencode(field string, m map[string]interface{}) (string, error) {
	b, err := marshalJSON(m)
	if err != nil {
		return "", &Error{Kind: ErrKindValidation, Message: field + " JSON 编码失败", Err: err}
	}
	return string(b), nil
}

func checkJSONLength(field, encoded string, max int) error {
	if textLen(encoded) > max {
		return validationError("%s 序列化后长度不能超过 %d", field, max)
	}
	return nil
}
