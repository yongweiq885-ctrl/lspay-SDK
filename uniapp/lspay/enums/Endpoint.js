/**
 * 立收通接口端点
 *
 * 路径来自平台公开接口列表：
 *   订单查询      https://api.lspay.tech/gateway/query
 *   订单退款      https://api.lspay.tech/gateway/refund
 *   退款订单查询  https://api.lspay.tech/gateway/refund/query
 */
const Endpoint = Object.freeze({
  UNIFIED_ORDER: 'gateway/unifiedorder',
  QUERY_ORDER:   'gateway/query',
  REFUND:        'gateway/refund',
  QUERY_REFUND:  'gateway/refund/query',
  DELAY_TRANS_CONFIRM: 'gateway/delaytrans/confirm',
  DELAY_TRANS_CONFIRM_REFUND: 'gateway/delaytrans/confirmrefund',
  DELAY_TRANS_UNCONFIRM_QUERY: 'gateway/delaytrans/unconfirm/query',
  DELAY_TRANS_CREATE_TASK: 'gateway/delaytrans/createtask',
  QUICK_PAY_BIND_APPLY: 'gateway/quickpay/buckleapply',
  QUICK_PAY_BIND_CONFIRM: 'gateway/quickpay/buckleconfirm',
  QUICK_PAY_CONFIRM: 'gateway/quickpay/payconfirm',
  QUICK_PAY_UNBIND: 'gateway/quickpay/payunbind',
  QUICK_PAY_SMS_CHECK: 'gateway/quickpay/smscheck',
  ELECTRONIC_ACCOUNT_USER_OPEN: 'gateway/user/personapply',
  ELECTRONIC_ACCOUNT_BUSINESS_OPEN: 'gateway/user/personconfirm',
  ELECTRONIC_ACCOUNT_BUSINESS_CHANGE: 'gateway/user/personmodify',
  ELECTRONIC_ACCOUNT_USER_QUERY: 'gateway/user/query',
  ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY: 'gateway/user/settlement/query',
  ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW: 'gateway/user/balance/withdraw',
  ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY: 'gateway/user/balance/withdraw/single/query',
  ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY: 'gateway/user/balance/withdraw/query',
  BALANCE_QUERY: 'gateway/user/balance/query',
});

module.exports = Endpoint;
