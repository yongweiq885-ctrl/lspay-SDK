package lspay

// 支付方式（way_code）。标注“直连”的代码只适用于已确认直连收单的商户，其他商户用间连代码。
const (
	WayCodeAliBar       = "ALI_BAR"        // 支付宝条码，扫码枪反扫
	WayCodeAliPub       = "ALI_PUB"        // 支付宝 JSAPI（间连，线上扫码推荐）
	WayCodeAliApp       = "ALI_APP"        // 支付宝 APP（直连）
	WayCodeAliH5        = "ALI_H5"         // 支付宝 H5（直连）
	WayCodeAliLiteH5    = "ALI_LITE_H5"    // 商户 App 或 H5 唤起支付宝收银台
	WayCodeAliPC        = "ALI_PC"         // 支付宝电脑网站（直连）
	WayCodeAliQR        = "ALI_QR"         // 支付宝扫码（间连，可在支付宝外拉起收银台）
	WayCodeAliLite      = "ALI_LITE"       // 支付宝小程序（直连）
	WayCodeWxBar        = "WX_BAR"         // 微信条码，扫码枪反扫
	WayCodeWxPub        = "WX_PUB"         // 微信 JSAPI（间连，扫码或公众号）
	WayCodeWxLite       = "WX_LITE"        // 微信小程序内支付
	WayCodeWxApp        = "WX_APP"         // 微信 APP（直连）
	WayCodeWxLiteH5     = "WX_LITE_H5"     // 商户 App 或 H5 唤起微信收银台
	WayCodeWxLiteJump   = "WX_LITE_JUMP"   // 自有小程序跳立收通收银台
	WayCodeWxNative     = "WX_NATIVE"      // 微信扫码（直连）
	WayCodeYsfBar       = "YSF_BAR"        // 云闪付条码，扫码枪反扫
	WayCodeYsfJsapi     = "YSF_JSAPI"      // 云闪付线上（小程序或扫码）
	WayCodeCupQR        = "CUP_QR"         // 云闪付扫码
	WayCodeBankFast     = "BANK_FAST"      // 银行卡快捷支付
	WayCodeBankFastJump = "BANK_FAST_JUMP" // 银行卡快捷支付收银台
)

// 业务代码，由对应请求自动填写。
const (
	WayCodeApply               = "APPLY"                 // 电子账户开户、业务开通、变更、查询
	WayCodeBalancePay          = "BALANCE_PAY"           // 余额支付
	WayCodeBalanceQuery        = "BALANCE_QUERY"         // 余额查询
	WayCodeSettleQuery         = "SETTLE_QUERY"          // 结算记录查询
	WayCodeBalanceWithdraw     = "BALANCE_WITHDRAW"      // 余额提现
	WayCodeWithdrawSingleQuery = "WITHDRAW_SINGLE_QUERY" // 单笔提现查询
	WayCodeWithdrawQuery       = "WITHDRAW_QUERY"        // 批量提现查询
)

var paymentMethods = []string{
	WayCodeAliBar, WayCodeAliPub, WayCodeAliApp, WayCodeAliH5, WayCodeAliLiteH5,
	WayCodeAliPC, WayCodeAliQR, WayCodeAliLite, WayCodeWxBar, WayCodeWxPub,
	WayCodeWxLite, WayCodeWxApp, WayCodeWxLiteH5, WayCodeWxLiteJump, WayCodeWxNative,
	WayCodeYsfBar, WayCodeYsfJsapi, WayCodeCupQR, WayCodeBankFast, WayCodeBankFastJump,
}

// PaymentMethods 返回统一下单可用的支付方式。
func PaymentMethods() []string {
	out := make([]string, len(paymentMethods))
	copy(out, paymentMethods)
	return out
}

// IsPaymentMethod 判断 way_code 能否用于统一下单。
func IsPaymentMethod(code string) bool {
	for _, c := range paymentMethods {
		if c == code {
			return true
		}
	}
	return false
}
