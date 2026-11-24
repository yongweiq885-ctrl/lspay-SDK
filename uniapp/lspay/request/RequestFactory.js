const Endpoint = require('../enums/Endpoint');
const WayCode = require('../enums/WayCode');
const Requests = require('./Requests');

const registry = {
  [Endpoint.UNIFIED_ORDER]: Requests.UnifiedOrderRequest,
  [Endpoint.QUERY_ORDER]: Requests.QueryOrderRequest,
  [Endpoint.REFUND]: Requests.RefundRequest,
  [Endpoint.QUERY_REFUND]: Requests.QueryRefundRequest,
  [Endpoint.DELAY_TRANS_CONFIRM]: Requests.DelayTransConfirmRequest,
  [Endpoint.DELAY_TRANS_CONFIRM_REFUND]: Requests.DelayTransConfirmRefundRequest,
  [Endpoint.DELAY_TRANS_UNCONFIRM_QUERY]: Requests.DelayTransUnconfirmQueryRequest,
  [Endpoint.DELAY_TRANS_CREATE_TASK]: Requests.DelayTransCreateTaskRequest,
  [Endpoint.QUICK_PAY_BIND_APPLY]: Requests.QuickPayBindApplyRequest,
  [Endpoint.QUICK_PAY_BIND_CONFIRM]: Requests.QuickPayBindConfirmRequest,
  [Endpoint.QUICK_PAY_CONFIRM]: Requests.QuickPayConfirmRequest,
  [Endpoint.QUICK_PAY_UNBIND]: Requests.QuickPayUnbindRequest,
  [Endpoint.QUICK_PAY_SMS_CHECK]: Requests.QuickPaySmsCheckRequest,
  [Endpoint.BALANCE_QUERY]: Requests.BalanceQueryRequest,
  [Endpoint.ELECTRONIC_ACCOUNT_USER_OPEN]: Requests.ElectronicAccountUserOpenRequest,
  [Endpoint.ELECTRONIC_ACCOUNT_BUSINESS_OPEN]: Requests.ElectronicAccountBusinessOpenRequest,
  [Endpoint.ELECTRONIC_ACCOUNT_BUSINESS_CHANGE]: Requests.ElectronicAccountBusinessChangeRequest,
  [Endpoint.ELECTRONIC_ACCOUNT_USER_QUERY]: Requests.ElectronicAccountUserQueryRequest,
  [Endpoint.ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY]: Requests.ElectronicAccountSettlementQueryRequest,
  [Endpoint.ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW]: Requests.ElectronicAccountBalanceWithdrawRequest,
  [Endpoint.ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY]: Requests.ElectronicAccountSingleWithdrawQueryRequest,
  [Endpoint.ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY]: Requests.ElectronicAccountBatchWithdrawQueryRequest,
};

function setterName(field) {
  return `set${field.split('_').map(x => x.charAt(0).toUpperCase() + x.slice(1)).join('')}`;
}
const RequestFactory = {
  supports(funcCode) { return Object.prototype.hasOwnProperty.call(registry, String(funcCode)); },
  fromArray(funcCode, params = {}) {
    let Cls = registry[String(funcCode)];
    if (!Cls) return null;
    if (funcCode === Endpoint.UNIFIED_ORDER) {
      if (params.way_code === WayCode.BANK_FAST) Cls = Requests.QuickPayApplyRequest;
      if (params.way_code === WayCode.BALANCE_PAY) Cls = Requests.BalancePayRequest;
    }
    const request = new Cls();
    const extras = {};
    Object.keys(params).forEach(field => {
      const setter = setterName(field);
      if (typeof request[setter] === 'function') request[setter](params[field]);
      else extras[field] = params[field];
    });
    if (Object.keys(extras).length) request.setExtendInfo(extras);
    return request;
  },
};
module.exports = RequestFactory;
