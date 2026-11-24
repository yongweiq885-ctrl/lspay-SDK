package lspay

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func mustParams(t *testing.T, r Request) map[string]string {
	t.Helper()
	p, err := r.Params()
	if err != nil {
		t.Fatalf("%T 校验失败: %v", r, err)
	}
	return p
}

func expectValidationError(t *testing.T, r Request, contains string) {
	t.Helper()
	_, err := r.Params()
	if !IsKind(err, ErrKindValidation) {
		t.Fatalf("%T 期望校验错误，实际 %v", r, err)
	}
	if contains != "" && !strings.Contains(err.Error(), contains) {
		t.Fatalf("%T 错误信息不含 %q: %v", r, contains, err)
	}
}

func TestSignMatchesCrossLanguageFixture(t *testing.T) {
	raw, err := os.ReadFile(filepath.Join("..", "_verify", "fixtures", "cross_lang.json"))
	if err != nil {
		t.Fatal(err)
	}
	var fixture struct {
		Secret string `json:"secret"`
		Tests  []struct {
			Name       string                 `json:"name"`
			Data       map[string]interface{} `json:"data"`
			ExpectSign string                 `json:"expect_sign"`
		} `json:"tests"`
	}
	if err := json.Unmarshal(raw, &fixture); err != nil {
		t.Fatal(err)
	}
	for _, tc := range fixture.Tests {
		data := map[string]string{}
		for k, v := range tc.Data {
			if v != nil {
				data[k] = v.(string)
			}
		}
		if got := Sign(data, fixture.Secret); got != tc.ExpectSign {
			t.Errorf("%s: got %s, want %s", tc.Name, got, tc.ExpectSign)
		}
	}
}

func TestVerifySign(t *testing.T) {
	data := map[string]string{"a": "1", "b": "2"}
	data["sign"] = strings.ToLower(Sign(data, "k"))
	if !VerifySign(data, "k") {
		t.Fatal("小写签名应通过")
	}
	data["a"] = "9"
	if VerifySign(data, "k") {
		t.Fatal("篡改后应失败")
	}
	if VerifySign(map[string]string{"a": "1"}, "k") {
		t.Fatal("缺少 sign 应失败")
	}
}

func TestContractCounts(t *testing.T) {
	if n := len(Endpoints()); n != 22 {
		t.Fatalf("接口数 %d", n)
	}
	if n := len(PaymentMethods()); n != 20 {
		t.Fatalf("支付方式数 %d", n)
	}
}

func TestUnifiedOrderValidation(t *testing.T) {
	base := func() *UnifiedOrderRequest {
		return &UnifiedOrderRequest{MchOrderNo: "O1", WayCode: WayCodeWxPub, Amount: 100, Subject: "商品", Body: "描述"}
	}

	r := base()
	r.ChannelExtra = &UnifiedOrderChannelExtra{OpenID: "oid"}
	p := mustParams(t, r)
	if p["channel_extra"] != `{"openid":"oid"}` || p["currency"] != "cny" || p["amount"] != "100" {
		t.Fatalf("参数不符: %v", p)
	}
	if _, ok := p["division_mode"]; ok {
		t.Fatal("division_mode=0 不应发送")
	}

	expectValidationError(t, base(), "channel_extra 必填")

	r = base()
	r.ChannelExtra = "{}"
	expectValidationError(t, r, "channel_extra.openid")

	r = base()
	r.ChannelExtra = map[string]interface{}{"pay_data_type": PayDataTypeCodeImgURL}
	mustParams(t, r)

	r = base()
	r.WayCode = WayCodeBalanceQuery
	expectValidationError(t, r, "支付方式清单")

	r = base()
	r.ChannelExtra = `{"pay_data_type":"qr"}`
	expectValidationError(t, r, "pay_data_type")

	r = base()
	r.ChannelExtra = `[1,2]`
	expectValidationError(t, r, "JSON 对象")

	r = base()
	r.ChannelExtra = &UnifiedOrderChannelExtra{PayDataType: PayDataTypePayURL}
	r.DivisionMode = 2
	expectValidationError(t, r, "div_list")

	r = base()
	r.ChannelExtra = `{"pay_data_type":"pay_url","div_list":[]}`
	r.DivisionMode = 1
	expectValidationError(t, r, "div_list")

	r = base()
	r.ChannelExtra = `{"pay_data_type":"pay_url","div_list":[{"trans_in":"A","div_amt":1.5}]}`
	expectValidationError(t, r, "div_amt")

	r = base()
	r.Amount = 0
	expectValidationError(t, r, "amount")

	r = base()
	r.NotifyURL = "ftp://x"
	expectValidationError(t, r, "notify_url")

	r = base()
	r.ClientIP = "::1"
	expectValidationError(t, r, "IPv4")
}

func TestChannelExtraKeepsIntegersAndCharacters(t *testing.T) {
	r := &UnifiedOrderRequest{
		MchOrderNo: "O1", WayCode: WayCodeAliQR, Amount: 100, Subject: "商品", Body: "描述",
		DivisionMode: 1,
		ChannelExtra: `{"pay_data_type": "pay_url", "div_list": [{"trans_in": "350873420280024268", "div_amt": 10, "description": "A&B <分账>"}]}`,
	}
	extra := mustParams(t, r)["channel_extra"]
	if !strings.Contains(extra, `"div_amt":10`) || strings.Contains(extra, "10.0") {
		t.Fatalf("整数被改写: %s", extra)
	}
	if !strings.Contains(extra, "A&B <分账>") {
		t.Fatalf("字符被转义: %s", extra)
	}
}

func TestQuickPayApplyAndBalancePay(t *testing.T) {
	mustParams(t, &QuickPayApplyRequest{
		MchOrderNo: "P1", Amount: 1, Subject: "快捷", Body: "快捷",
		ChannelExtra: &UnifiedOrderChannelExtra{ChannelUser: "U", ChannelCardID: "C"},
	})
	expectValidationError(t, &QuickPayApplyRequest{MchOrderNo: "P1", Amount: 1, Subject: "快捷", Body: "快捷", ChannelExtra: "{}"}, "channel_user")

	balance := &BalancePayRequest{
		MchOrderNo: "B1", Amount: 100, Subject: "余额", Body: "余额",
		ChannelExtra: &BalancePayChannelExtra{TransOut: "A", DivList: []DivisionReceiver{{TransIn: "B", DivAmt: 100}}},
	}
	p := mustParams(t, balance)
	if p["way_code"] != WayCodeBalancePay || p["division_mode"] != "1" {
		t.Fatalf("余额支付固定参数不符: %v", p)
	}
	balance.ChannelExtra = &BalancePayChannelExtra{TransOut: "A", DivList: []DivisionReceiver{{TransIn: "B", DivAmt: 60}, {TransIn: "C", DivAmt: 60}}}
	expectValidationError(t, balance, "总和")
	balance.ChannelExtra = &BalancePayChannelExtra{TransOut: "A", DivList: []DivisionReceiver{{TransIn: "B"}}}
	expectValidationError(t, balance, "div_amt")

	q := &QuickPayApplyRequest{
		MchOrderNo: "P1", Amount: 1, Subject: "快捷", Body: "快捷",
		ChannelExtra: &UnifiedOrderChannelExtra{ChannelUser: "U", ChannelCardID: "C"},
		ExtendInfo:   map[string]string{"way_code": "WX_PUB"},
	}
	expectValidationError(t, q, "扩展参数")
}

func TestPaymentQueriesAndRefund(t *testing.T) {
	mustParams(t, &QueryOrderRequest{MchOrderNo: "O1"})
	expectValidationError(t, &QueryOrderRequest{}, "至少填写一个")
	mustParams(t, &RefundRequest{MchRefundNo: "R1", MchOrderNo: "O1", RefundAmount: 1, RefundReason: "用户申请"})
	expectValidationError(t, &RefundRequest{MchRefundNo: "R1", MchOrderNo: "O1", RefundReason: "x"}, "refund_amount")
	mustParams(t, &QueryRefundRequest{OrderRefundID: "R1"})
	expectValidationError(t, &QueryRefundRequest{}, "至少填写一个")
}

func TestDelayTrans(t *testing.T) {
	p := mustParams(t, &DelayTransConfirmRequest{
		MchOrderNo: "O1", OrderID: "C1",
		ChannelExtra: &DivisionChannelExtra{DivList: []DivisionReceiver{{TransIn: "A", DivAmt: 10}}},
	})
	if p["channel_extra"] != `{"div_list":[{"div_amt":10,"trans_in":"A"}]}` {
		t.Fatalf("channel_extra: %s", p["channel_extra"])
	}
	expectValidationError(t, &DelayTransConfirmRequest{MchOrderNo: "O1"}, "order_id")
	mustParams(t, &DelayTransConfirmRefundRequest{ConfirmOrderID: "C1", ConfirmRefundNo: "CR1"})
	mustParams(t, &DelayTransUnconfirmQueryRequest{})
	mustParams(t, &DelayTransCreateTaskRequest{AccountNo: "A1", AccountName: "张三", OrderNo: "T1", Amount: 100})
	expectValidationError(t, &DelayTransCreateTaskRequest{AccountNo: "A1", AccountName: "张三", OrderNo: "T1"}, "amount")
}

func TestQuickPay(t *testing.T) {
	bind := &QuickPayBindChannelExtra{
		MchUserID: "U", CardID: "6222", CardName: "张三", CertID: "ID",
		CertValidityType: "0", CertBeginDate: "20200101", CertEndDate: "20300101",
		CardMp: "13800000000", DcType: "D",
	}
	p := mustParams(t, &QuickPayBindApplyRequest{OrderID: "B1", ChannelExtra: bind})
	if p["way_code"] != WayCodeBankFast || !strings.Contains(p["channel_extra"], `"cert_type":"00"`) {
		t.Fatalf("绑卡参数不符: %v", p)
	}
	bad := *bind
	bad.CertEndDate = ""
	expectValidationError(t, &QuickPayBindApplyRequest{OrderID: "B1", ChannelExtra: &bad}, "cert_end_date")

	confirm := &QuickPayBindConfirmChannelExtra{MchUserID: "U", CardID: "6222", CardName: "张三", CertID: "ID", CardMp: "13800000000", DcType: "D"}
	mustParams(t, &QuickPayBindConfirmRequest{OrderID: "B1", ChannelExtra: confirm, VerifyCode: "123456"})
	expectValidationError(t, &QuickPayBindConfirmRequest{OrderID: "B1", ChannelExtra: confirm, VerifyCode: "12"}, "verify_code")

	mustParams(t, &QuickPayConfirmRequest{MchOrderNo: "P1", VerifyCode: "123456"})
	expectValidationError(t, &QuickPayConfirmRequest{MchOrderNo: "P1", VerifyCode: "abcdef"}, "6 位数字")
	mustParams(t, &QuickPaySmsCheckRequest{PayOrderID: "P2", VerifyCode: "123456"})
	mustParams(t, &QuickPayUnbindRequest{OrderID: "B3", ChannelExtra: &QuickPayUnbindChannelExtra{MchUserID: "U", ChannelCardID: "C"}})
	expectValidationError(t, &QuickPayUnbindRequest{OrderID: "B3"}, "mch_user_id")
}

func TestElectronicAccount(t *testing.T) {
	open := &ElectronicAccountUserOpenRequest{
		OrderID: "U1", AccountName: "张三", AccountType: AccountTypePersonal,
		RelationType: RelationTypeUser, AccountAlias: "用户", DivisionProfit: "0.1",
		ChannelExtra: &ElectronicAccountOpenChannelExtra{UserInfo: &ElectronicAccountUserInfo{
			Name: "张三", CertNo: "ID", CertValidityType: "0", CertBeginDate: "20200101",
			CertEndDate: "20300101", MobileNo: "13800000000", Email: "a@example.com",
		}},
	}
	p := mustParams(t, open)
	if p["way_code"] != WayCodeApply || p["account_type"] != "1" {
		t.Fatalf("开户参数不符: %v", p)
	}
	open.AccountType = AccountTypeEnterprise
	expectValidationError(t, open, "mer_info")
	open.AccountType = AccountTypePersonal
	open.DivisionProfit = "4.1"
	expectValidationError(t, open, "division_profit")

	business := &ElectronicAccountBusinessOpenRequest{
		OrderID: "BO1", AccountNo: "A1",
		ChannelExtra: &ElectronicAccountBusinessChannelExtra{
			CardInfo: &ElectronicAccountSettlementCard{
				CardType: "0", CardName: "公司", CardNo: "6222", ProvID: "110000", AreaID: "110100",
				Mp: "13800000000", BankCode: "ABC", BranchName: "支行",
			},
			SettleConfig: &ElectronicAccountSettleConfig{SettleCycle: "T1", SettleStatus: 1},
			CashConfig:   []ElectronicAccountCashConfig{{CashType: "D0", SwitchState: 1, OutFeeFlag: 2}},
		},
	}
	mustParams(t, business)
	business.ChannelExtra = `{"card_info":{"card_type":"1","card_name":"张三","card_no":"6222"},"settle_config":{"settle_cycle":"D1","settle_status":1},"cash_config":[{"cash_type":"D1","switch_state":1,"out_fee_flag":1}]}`
	expectValidationError(t, business, "card_info")

	mustParams(t, &ElectronicAccountBusinessChangeRequest{
		OrderID: "BC1", AccountNo: "A1",
		ChannelExtra: &ElectronicAccountBusinessChangeChannelExtra{
			CardInfo:     &ElectronicAccountModifiedCard{CardType: "1", CardName: "张三", CardNo: "6222"},
			SettleConfig: &ElectronicAccountSettleConfig{SettleCycle: "D1", SettleStatus: 1},
			CashConfig:   []ElectronicAccountCashConfig{{CashType: "D1", SwitchState: 1, OutFeeFlag: 1}},
		},
	})
	if p := mustParams(t, &ElectronicAccountUserQueryRequest{AccountNo: "A1"}); p["way_code"] != WayCodeApply {
		t.Fatalf("用户查询 way_code: %v", p)
	}
	mustParams(t, &ElectronicAccountSettlementQueryRequest{OrderID: "S1", AccountNo: "A1", BeginDate: "20260101", EndDate: "20260131"})
	expectValidationError(t, &ElectronicAccountSettlementQueryRequest{OrderID: "S1", AccountNo: "A1", BeginDate: "20260101", EndDate: "20260301"}, "30 天")
	mustParams(t, &ElectronicAccountBalanceWithdrawRequest{OrderID: "W1", AccountNo: "A1", WithdrawAmount: "0.01"})
	expectValidationError(t, &ElectronicAccountBalanceWithdrawRequest{OrderID: "W1", AccountNo: "A1", WithdrawAmount: "0.00"}, "withdraw_amount")
	expectValidationError(t, &ElectronicAccountBalanceWithdrawRequest{OrderID: "W1", AccountNo: "A1", WithdrawAmount: "1.234"}, "withdraw_amount")
	mustParams(t, &ElectronicAccountSingleWithdrawQueryRequest{AccountNo: "A1", WithdrawDate: "20260101", OrderID: "W1"})
	mustParams(t, &ElectronicAccountBatchWithdrawQueryRequest{AccountNo: "A1", BeginDate: "20260101", EndDate: "20260109"})
	expectValidationError(t, &ElectronicAccountBatchWithdrawQueryRequest{AccountNo: "A1", BeginDate: "20260109", EndDate: "20260101"}, "begin_date")
	mustParams(t, &BalanceQueryRequest{OrderID: "Q1", AccountNo: "A1"})
}

func TestMoney(t *testing.T) {
	for in, want := range map[string]int64{"0.01": 1, "12.34": 1234, "12.3": 1230, "100": 10000, "-1.05": -105} {
		got, err := YuanToFen(in)
		if err != nil || got != want {
			t.Errorf("YuanToFen(%q) = %d, %v", in, got, err)
		}
	}
	for _, in := range []string{"1.234", "abc", "", "01.00"} {
		if _, err := YuanToFen(in); err == nil {
			t.Errorf("YuanToFen(%q) 应失败", in)
		}
	}
	for in, want := range map[int64]string{1: "0.01", 1234: "12.34", 10000: "100.00", -105: "-1.05"} {
		if got := FenToYuan(in); got != want {
			t.Errorf("FenToYuan(%d) = %s", in, got)
		}
	}
}

func TestSanitizeForLog(t *testing.T) {
	safe := SanitizeForLog(map[string]string{
		"sign":          "1234567890ABCDEF",
		"channel_extra": `{"card_no":"6222000012345678","pay_data_type":"cashier"}`,
	})
	b, _ := json.Marshal(safe)
	text := string(b)
	if strings.Contains(text, "6222000012345678") || strings.Contains(text, "1234567890ABCDEF") || !strings.Contains(text, "pay_data_type") {
		t.Fatalf("脱敏结果不符: %s", text)
	}
}
