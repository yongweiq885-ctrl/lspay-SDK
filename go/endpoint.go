package lspay

// 接口路径。
const (
	EndpointUnifiedOrder                         = "gateway/unifiedorder"
	EndpointQueryOrder                           = "gateway/query"
	EndpointRefund                               = "gateway/refund"
	EndpointQueryRefund                          = "gateway/refund/query"
	EndpointDelayTransConfirm                    = "gateway/delaytrans/confirm"
	EndpointDelayTransConfirmRefund              = "gateway/delaytrans/confirmrefund"
	EndpointDelayTransUnconfirmQuery             = "gateway/delaytrans/unconfirm/query"
	EndpointDelayTransCreateTask                 = "gateway/delaytrans/createtask"
	EndpointQuickPayBindApply                    = "gateway/quickpay/buckleapply"
	EndpointQuickPayBindConfirm                  = "gateway/quickpay/buckleconfirm"
	EndpointQuickPayConfirm                      = "gateway/quickpay/payconfirm"
	EndpointQuickPayUnbind                       = "gateway/quickpay/payunbind"
	EndpointQuickPaySmsCheck                     = "gateway/quickpay/smscheck"
	EndpointElectronicAccountUserOpen            = "gateway/user/personapply"
	EndpointElectronicAccountBusinessOpen        = "gateway/user/personconfirm"
	EndpointElectronicAccountBusinessChange      = "gateway/user/personmodify"
	EndpointElectronicAccountUserQuery           = "gateway/user/query"
	EndpointElectronicAccountSettlementQuery     = "gateway/user/settlement/query"
	EndpointElectronicAccountBalanceWithdraw     = "gateway/user/balance/withdraw"
	EndpointElectronicAccountSingleWithdrawQuery = "gateway/user/balance/withdraw/single/query"
	EndpointElectronicAccountBatchWithdrawQuery  = "gateway/user/balance/withdraw/query"
	EndpointBalanceQuery                         = "gateway/user/balance/query"
)

// Endpoints 返回全部接口路径。
func Endpoints() []string {
	return []string{
		EndpointUnifiedOrder, EndpointQueryOrder, EndpointRefund, EndpointQueryRefund,
		EndpointDelayTransConfirm, EndpointDelayTransConfirmRefund,
		EndpointDelayTransUnconfirmQuery, EndpointDelayTransCreateTask,
		EndpointQuickPayBindApply, EndpointQuickPayBindConfirm, EndpointQuickPayConfirm,
		EndpointQuickPayUnbind, EndpointQuickPaySmsCheck,
		EndpointElectronicAccountUserOpen, EndpointElectronicAccountBusinessOpen,
		EndpointElectronicAccountBusinessChange, EndpointElectronicAccountUserQuery,
		EndpointElectronicAccountSettlementQuery, EndpointElectronicAccountBalanceWithdraw,
		EndpointElectronicAccountSingleWithdrawQuery, EndpointElectronicAccountBatchWithdrawQuery,
		EndpointBalanceQuery,
	}
}
