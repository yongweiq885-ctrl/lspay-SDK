const BaseRequest = require('./BaseRequest');
const Endpoint = require('../enums/Endpoint');
const WayCode = require('../enums/WayCode');
const R = require('../response/Responses');
const V = require('./validators');

function defineAccessors(Cls, fields) {
  Object.keys(fields).forEach(field => {
    const suffix = fields[field];
    Cls.prototype[`set${suffix}`] = function (value) {
      if (field === 'channel_extra') value = V.jsonObject(value, field);
      else if (field === 'ext_param' && value && typeof value === 'object') value = JSON.stringify(value);
      else if (typeof value === 'string') value = value.trim();
      this[field] = value;
      return this;
    };
    Cls.prototype[`get${suffix}`] = function () { return this[field]; };
  });
}
function endpointClass(name, endpoint, response, fields, fixed) {
  const Cls = { [name]: class extends BaseRequest {
    constructor() {
      super();
      Object.keys(fields).forEach(f => { this[f] = undefined; });
      Object.assign(this, fixed || {});
    }
    getFunctionCode() { return endpoint; }
    getResponseClass() { return response; }
  } }[name];
  defineAccessors(Cls, fields);
  return Cls;
}
const F = {
  mch_order_no: 'MchOrderNo', pay_order_id: 'PayOrderId', mch_refund_no: 'MchRefundNo',
  order_refund_id: 'OrderRefundId', refund_amount: 'RefundAmount', currency: 'Currency',
  refund_reason: 'RefundReason', client_ip: 'ClientIp', notify_url: 'NotifyUrl',
  return_url: 'ReturnUrl', channel_extra: 'ChannelExtra', ext_param: 'ExtParam',
  way_code: 'WayCode', amount: 'Amount', subject: 'Subject', body: 'Body',
  expired_time: 'ExpiredTime', division_mode: 'DivisionMode', polling_mode: 'PollingMode',
  order_id: 'OrderId', account_no: 'AccountNo', account_name: 'AccountName',
  order_no: 'OrderNo', remark: 'Remark', verify_code: 'VerifyCode',
  confirm_order_id: 'ConfirmOrderId', confirm_refund_no: 'ConfirmRefundNo',
  account_type: 'AccountType', relation_type: 'RelationType', relation_type_name: 'RelationTypeName',
  account_alias: 'AccountAlias', division_profit: 'DivisionProfit', begin_date: 'BeginDate',
  end_date: 'EndDate', page_size: 'PageSize', page_num: 'PageNum',
  withdraw_amount: 'WithdrawAmount', withdraw_date: 'WithdrawDate',
};

class UnifiedOrderRequest extends endpointClass('UnifiedOrderRequest', Endpoint.UNIFIED_ORDER, R.UnifiedOrderResponse, {
  mch_order_no: F.mch_order_no, way_code: F.way_code, amount: F.amount, currency: F.currency,
  subject: F.subject, body: F.body, channel_extra: F.channel_extra, client_ip: F.client_ip,
  notify_url: F.notify_url, return_url: F.return_url, expired_time: F.expired_time,
  division_mode: F.division_mode, ext_param: F.ext_param, polling_mode: F.polling_mode,
}, { currency: 'cny' }) {
  validate() {
    V.required('mch_order_no', this.mch_order_no, 30);
    V.required('way_code', this.way_code, 30);
    if (!Object.prototype.hasOwnProperty.call(this.allowedWayCodes(), this.way_code)) {
      V.fail('way_code 不在统一下单支付方式清单中');
    }
    V.integer('amount', this.amount, true);
    if (!/^[a-zA-Z]{3}$/.test(String(this.currency || ''))) V.fail('currency 必须是 3 位字母货币代码');
    V.required('subject', this.subject, 64); V.required('body', this.body, 256);
    V.optional('client_ip', this.client_ip, 32); V.ipv4('client_ip', this.client_ip);
    V.optional('notify_url', this.notify_url, 128); V.httpUrl('notify_url', this.notify_url);
    V.optional('return_url', this.return_url, 128); V.httpUrl('return_url', this.return_url);
    if (this.expired_time != null) V.integer('expired_time', this.expired_time, true);
    if (this.division_mode != null && ![0, 1, 2].includes(Number(this.division_mode))) V.fail('division_mode 只允许 0、1、2');
    if (this.polling_mode != null && ![0, 1, 2, 3].includes(Number(this.polling_mode))) V.fail('polling_mode 只允许 0、1、2、3');
    V.required('channel_extra', this.channel_extra, 256);
    const extra = JSON.parse(this.channel_extra);
    if (extra.pay_data_type != null && !['cashier', 'pay_url', 'wxapp', 'aliapp', 'ysfapp', 'code_url', 'code_img_url', 'none'].includes(extra.pay_data_type)) {
      V.fail('channel_extra.pay_data_type 非法');
    }
    const required = { ALI_LITE: 'buyer_id', WX_LITE: 'openid', WX_BAR: 'auth_code', ALI_BAR: 'auth_code', YSF_BAR: 'auth_code' };
    if (extra.pay_data_type == null || String(extra.pay_data_type).trim() === '') {
      required.ALI_PUB = 'buyer_id';
      required.WX_PUB = 'openid';
    }
    if (required[this.way_code]) V.required(`channel_extra.${required[this.way_code]}`, extra[required[this.way_code]]);
    if (this.way_code === WayCode.BANK_FAST) {
      V.required('channel_extra.channel_user', extra.channel_user);
      V.required('channel_extra.channel_card_id', extra.channel_card_id);
    }
    if ([1, 2].includes(Number(this.division_mode)) && (!Array.isArray(extra.div_list) || !extra.div_list.length)) {
      V.fail('division_mode 为 1 或 2 时 channel_extra.div_list 必填且不能为空');
    }
    if (extra.div_list != null && !Array.isArray(extra.div_list)) V.fail('channel_extra.div_list 必须是数组');
    if (Array.isArray(extra.div_list)) extra.div_list.forEach((x, i) => {
      if (!x || typeof x !== 'object' || Array.isArray(x)) V.fail(`channel_extra.div_list[${i}] 必须是 JSON 对象`);
      V.required(`channel_extra.div_list[${i}].trans_in`, x && x.trans_in);
      if (x && x.div_amt != null) V.integer(`channel_extra.div_list[${i}].div_amt`, x.div_amt, true);
    });
  }
  allowedWayCodes() { return WayCode.paymentMethods(); }
}
class QuickPayApplyRequest extends UnifiedOrderRequest {
  constructor() { super(); this.way_code = WayCode.BANK_FAST; }
  getResponseClass() { return R.QuickPayApplyResponse; }
  setWayCode(v) { if (String(v) !== WayCode.BANK_FAST) V.fail('way_code 固定为 BANK_FAST'); return super.setWayCode(v); }
}
class BalancePayRequest extends UnifiedOrderRequest {
  constructor() { super(); this.way_code = WayCode.BALANCE_PAY; this.division_mode = 1; }
  getResponseClass() { return R.BalancePayResponse; }
  setWayCode(v) { if (String(v) !== WayCode.BALANCE_PAY) V.fail('way_code 固定为 BALANCE_PAY'); return super.setWayCode(v); }
  setDivisionMode(v) { if (Number(v) !== 1) V.fail('division_mode 固定为 1'); return super.setDivisionMode(1); }
  allowedWayCodes() { return { [WayCode.BALANCE_PAY]: '余额支付' }; }
  validate() {
    super.validate();
    const x = JSON.parse(this.channel_extra || '{}');
    V.required('channel_extra.trans_out', x.trans_out);
    const total = (x.div_list || []).reduce((n, item) => n + V.integer('div_amt', item.div_amt, true), 0);
    if (total > Number(this.amount)) V.fail('channel_extra.div_list 的 div_amt 总和不能超过 amount');
  }
}

const QueryOrderRequest = endpointClass('QueryOrderRequest', Endpoint.QUERY_ORDER, R.QueryOrderResponse,
  { mch_order_no: F.mch_order_no, pay_order_id: F.pay_order_id });
QueryOrderRequest.prototype.validate = function () {
  if (!this.mch_order_no && !this.pay_order_id) V.fail('mch_order_no 与 pay_order_id 至少填写一个');
  V.optional('mch_order_no', this.mch_order_no, 30); V.optional('pay_order_id', this.pay_order_id, 30);
};
QueryOrderRequest.prototype.setPayOrderNo = QueryOrderRequest.prototype.setPayOrderId;
QueryOrderRequest.prototype.getPayOrderNo = QueryOrderRequest.prototype.getPayOrderId;
const RefundRequest = endpointClass('RefundRequest', Endpoint.REFUND, R.RefundResponse, {
  mch_refund_no: F.mch_refund_no, mch_order_no: F.mch_order_no, pay_order_id: F.pay_order_id,
  refund_amount: F.refund_amount, currency: F.currency, refund_reason: F.refund_reason,
  client_ip: F.client_ip, notify_url: F.notify_url, channel_extra: F.channel_extra, ext_param: F.ext_param,
}, { currency: 'cny' });
RefundRequest.prototype.validate = function () {
  V.required('mch_refund_no', this.mch_refund_no, 30);
  if (!this.mch_order_no && !this.pay_order_id) V.fail('mch_order_no 与 pay_order_id 至少填写一个');
  V.integer('refund_amount', this.refund_amount, true); V.required('refund_reason', this.refund_reason, 64);
  if (!/^[a-zA-Z]{3}$/.test(String(this.currency || ''))) V.fail('currency 必须是 3 位字母货币代码');
  V.ipv4('client_ip', this.client_ip); V.httpUrl('notify_url', this.notify_url);
};
RefundRequest.prototype.setPayOrderNo = RefundRequest.prototype.setPayOrderId;
RefundRequest.prototype.getPayOrderNo = RefundRequest.prototype.getPayOrderId;
const QueryRefundRequest = endpointClass('QueryRefundRequest', Endpoint.QUERY_REFUND, R.QueryRefundResponse,
  { mch_refund_no: F.mch_refund_no, order_refund_id: F.order_refund_id });
QueryRefundRequest.prototype.validate = function () {
  if (!this.mch_refund_no && !this.order_refund_id) V.fail('mch_refund_no 与 order_refund_id 至少填写一个');
};
QueryRefundRequest.prototype.setRefundOrderId = QueryRefundRequest.prototype.setOrderRefundId;
QueryRefundRequest.prototype.getRefundOrderId = QueryRefundRequest.prototype.getOrderRefundId;
QueryRefundRequest.prototype.setRefundOrderNo = QueryRefundRequest.prototype.setOrderRefundId;
QueryRefundRequest.prototype.getRefundOrderNo = QueryRefundRequest.prototype.getOrderRefundId;

const DelayTransConfirmRequest = endpointClass('DelayTransConfirmRequest', Endpoint.DELAY_TRANS_CONFIRM, R.DelayTransConfirmResponse,
  { pay_order_id: F.pay_order_id, mch_order_no: F.mch_order_no, order_id: F.order_id, channel_extra: F.channel_extra });
DelayTransConfirmRequest.prototype.validate = function () {
  if (!this.pay_order_id && !this.mch_order_no) V.fail('pay_order_id 与 mch_order_no 至少填写一个');
  V.required('order_id', this.order_id, 30);
};
const DelayTransConfirmRefundRequest = endpointClass('DelayTransConfirmRefundRequest', Endpoint.DELAY_TRANS_CONFIRM_REFUND, R.DelayTransConfirmRefundResponse,
  { confirm_order_id: F.confirm_order_id, confirm_refund_no: F.confirm_refund_no, channel_extra: F.channel_extra });
DelayTransConfirmRefundRequest.prototype.validate = function () {
  V.required('confirm_order_id', this.confirm_order_id, 30); V.required('confirm_refund_no', this.confirm_refund_no, 30);
};
const DelayTransUnconfirmQueryRequest = endpointClass('DelayTransUnconfirmQueryRequest', Endpoint.DELAY_TRANS_UNCONFIRM_QUERY, R.DelayTransUnconfirmQueryResponse, {});
const DelayTransCreateTaskRequest = endpointClass('DelayTransCreateTaskRequest', Endpoint.DELAY_TRANS_CREATE_TASK, R.DelayTransCreateTaskResponse,
  { account_no: F.account_no, account_name: F.account_name, order_no: F.order_no, amount: F.amount, notify_url: F.notify_url, remark: F.remark });
DelayTransCreateTaskRequest.prototype.validate = function () {
  V.required('account_no', this.account_no, 30); V.required('account_name', this.account_name, 30);
  V.required('order_no', this.order_no, 30); V.integer('amount', this.amount, true); V.httpUrl('notify_url', this.notify_url);
};

function fixedWayRequest(name, endpoint, response, fields, fixedWay = WayCode.BANK_FAST) {
  const C = endpointClass(name, endpoint, response, Object.assign({ way_code: F.way_code }, fields), { way_code: fixedWay });
  C.prototype.setWayCode = function (v) { if (String(v) !== fixedWay) V.fail(`way_code 固定为 ${fixedWay}`); this.way_code = fixedWay; return this; };
  C.prototype._validateFixed = function () { if (this.way_code !== fixedWay) V.fail(`way_code 固定为 ${fixedWay}`); };
  return C;
}
const QuickPayBindApplyRequest = fixedWayRequest('QuickPayBindApplyRequest', Endpoint.QUICK_PAY_BIND_APPLY, R.QuickPayBindApplyResponse,
  { order_id: F.order_id, client_ip: F.client_ip, channel_extra: F.channel_extra });
function validateBindBase(request) {
  request._validateFixed(); V.required('order_id', request.order_id, 30); V.ipv4('client_ip', request.client_ip);
  V.required('channel_extra', request.channel_extra, 256); return JSON.parse(request.channel_extra);
}
QuickPayBindApplyRequest.prototype.validate = function () {
  const x = validateBindBase(this);
  ['mch_user_id', 'card_id', 'card_name', 'cert_id', 'cert_validity_type', 'cert_begin_date', 'card_mp', 'dc_type']
    .forEach(k => V.required(`channel_extra.${k}`, x[k]));
  V.oneOf('channel_extra.cert_validity_type', String(x.cert_validity_type), ['0', '1']);
  V.oneOf('channel_extra.dc_type', String(x.dc_type).toUpperCase(), ['C', 'D', 'S', 'U']);
  V.date8('channel_extra.cert_begin_date', x.cert_begin_date);
  if (String(x.cert_validity_type) === '0') V.date8('channel_extra.cert_end_date', x.cert_end_date);
};
const QuickPayBindConfirmRequest = fixedWayRequest('QuickPayBindConfirmRequest', Endpoint.QUICK_PAY_BIND_CONFIRM, R.QuickPayBindConfirmResponse,
  { order_id: F.order_id, client_ip: F.client_ip, channel_extra: F.channel_extra, verify_code: F.verify_code });
QuickPayBindConfirmRequest.prototype.validate = function () {
  const x = validateBindBase(this);
  ['mch_user_id', 'card_id', 'card_name', 'cert_id', 'card_mp', 'dc_type'].forEach(k => V.required(`channel_extra.${k}`, x[k]));
  V.oneOf('channel_extra.dc_type', String(x.dc_type).toUpperCase(), ['C', 'D', 'S', 'U']);
  if (!/^\d{3,8}$/.test(String(this.verify_code || ''))) V.fail('verify_code 必须是 3-8 位数字');
};
const QuickPayConfirmRequest = fixedWayRequest('QuickPayConfirmRequest', Endpoint.QUICK_PAY_CONFIRM, R.QuickPayConfirmResponse,
  { client_ip: F.client_ip, pay_order_id: F.pay_order_id, mch_order_no: F.mch_order_no, verify_code: F.verify_code });
QuickPayConfirmRequest.prototype.validate = function () {
  this._validateFixed(); V.ipv4('client_ip', this.client_ip);
  if (!this.pay_order_id && !this.mch_order_no) V.fail('pay_order_id 与 mch_order_no 至少填写一个');
  if (!/^\d{6}$/.test(String(this.verify_code || ''))) V.fail('verify_code 必须是 6 位数字');
};
const QuickPaySmsCheckRequest = class QuickPaySmsCheckRequest extends QuickPayConfirmRequest {
  getFunctionCode() { return Endpoint.QUICK_PAY_SMS_CHECK; }
  getResponseClass() { return R.QuickPaySmsCheckResponse; }
};
const QuickPayUnbindRequest = fixedWayRequest('QuickPayUnbindRequest', Endpoint.QUICK_PAY_UNBIND, R.QuickPayUnbindResponse,
  { order_id: F.order_id, client_ip: F.client_ip, channel_extra: F.channel_extra });
QuickPayUnbindRequest.prototype.validate = function () {
  const x = validateBindBase(this);
  V.required('channel_extra.mch_user_id', x.mch_user_id);
  V.required('channel_extra.channel_card_id', x.channel_card_id);
};

const BalanceQueryRequest = fixedWayRequest('BalanceQueryRequest', Endpoint.BALANCE_QUERY, R.BalanceQueryResponse,
  { order_id: F.order_id, client_ip: F.client_ip, account_no: F.account_no }, WayCode.BALANCE_QUERY);
BalanceQueryRequest.prototype.validate = function () { this._validateFixed(); V.required('order_id', this.order_id, 30); V.required('account_no', this.account_no, 256); V.ipv4('client_ip', this.client_ip); };

const ElectronicAccountUserOpenRequest = fixedWayRequest('ElectronicAccountUserOpenRequest', Endpoint.ELECTRONIC_ACCOUNT_USER_OPEN, R.ElectronicAccountUserOpenResponse, {
  order_id: F.order_id, account_name: F.account_name, account_type: F.account_type, relation_type: F.relation_type,
  relation_type_name: F.relation_type_name, account_alias: F.account_alias, division_profit: F.division_profit,
  client_ip: F.client_ip, channel_extra: F.channel_extra,
}, WayCode.APPLY);
ElectronicAccountUserOpenRequest.prototype.validate = function () {
  this._validateFixed(); V.required('order_id', this.order_id, 30); V.required('account_name', this.account_name, 30);
  if (![1, 2, 3].includes(Number(this.account_type))) V.fail('account_type 只允许 1、2、3');
  const relations = ['SERVICE_PROVIDER', 'STORE', 'STAFF', 'STORE_OWNER', 'PARTNER', 'HEADQUARTER', 'BRAND', 'DISTRIBUTOR', 'USER', 'SUPPLIER', 'CUSTOM'];
  V.oneOf('relation_type', this.relation_type, relations);
  if (this.relation_type === 'CUSTOM') V.required('relation_type_name', this.relation_type_name, 30);
  V.required('account_alias', this.account_alias, 30);
  if (!/^(?:0(?:\.\d+)?|1(?:\.0+)?)$/.test(String(this.division_profit || ''))) V.fail('division_profit 必须是 0 到 1 之间的十进制比例');
  V.ipv4('client_ip', this.client_ip); V.required('channel_extra', this.channel_extra, 2048);
  const x = JSON.parse(this.channel_extra);
  if (Number(this.account_type) === 1) {
    if (!x.user_info) V.fail('account_type=1 时 channel_extra.user_info 必填');
    ['cert_type', 'name', 'cert_no', 'cert_validity_type', 'cert_begin_date', 'mobile_no']
      .forEach(k => V.required(`channel_extra.user_info.${k}`, x.user_info[k]));
    V.date8('channel_extra.user_info.cert_begin_date', x.user_info.cert_begin_date);
    if (String(x.user_info.cert_validity_type) === '0') V.date8('channel_extra.user_info.cert_end_date', x.user_info.cert_end_date);
  } else {
    if (!x.mer_info) V.fail('account_type=2 或 3 时 channel_extra.mer_info 必填');
    ['reg_name', 'short_name', 'license_code', 'license_validity_type', 'license_begin_date',
      'reg_prov_id', 'reg_area_id', 'reg_district_id', 'reg_detail', 'legal_name', 'legal_cert_type',
      'legal_cert_no', 'legal_cert_validity_type', 'legal_cert_begin_date', 'contact_name', 'contact_mobile']
      .forEach(k => V.required(`channel_extra.mer_info.${k}`, x.mer_info[k]));
  }
};
function businessRequest(name, endpoint, response, fixedWay) {
  const fields = { order_id: F.order_id, account_no: F.account_no, client_ip: F.client_ip, channel_extra: F.channel_extra };
  const fixed = {};
  if (fixedWay) { fields.way_code = F.way_code; fixed.way_code = fixedWay; }
  const C = endpointClass(name, endpoint, response, fields, fixed);
  C.prototype.validate = function () {
    if (fixedWay && this.way_code !== fixedWay) V.fail(`way_code 固定为 ${fixedWay}`);
    V.required('order_id', this.order_id, 30); V.required('account_no', this.account_no, 32); V.ipv4('client_ip', this.client_ip);
    V.required('channel_extra', this.channel_extra, 2048); const x = JSON.parse(this.channel_extra);
    if (!x.card_info || !x.settle_config || !Array.isArray(x.cash_config) || !x.cash_config.length) V.fail('channel_extra card_info、settle_config、cash_config 必填');
    ['card_type', 'card_name', 'card_no'].forEach(k => V.required(`channel_extra.card_info.${k}`, x.card_info[k]));
    ['settle_cycle', 'settle_status'].forEach(k => V.required(`channel_extra.settle_config.${k}`, x.settle_config[k]));
    V.oneOf('channel_extra.settle_config.settle_cycle', x.settle_config.settle_cycle, ['T1', 'D1']);
    x.cash_config.forEach((item, i) => {
      if (!item || typeof item !== 'object' || Array.isArray(item)) V.fail(`channel_extra.cash_config[${i}] 必须是 JSON 对象`);
      ['cash_type', 'switch_state', 'out_fee_flag'].forEach(k => V.required(`channel_extra.cash_config[${i}].${k}`, item[k]));
      V.oneOf(`channel_extra.cash_config[${i}].cash_type`, item.cash_type, ['D0', 'D1', 'DM']);
    });
  };
  return C;
}
const ElectronicAccountBusinessOpenRequest = businessRequest('ElectronicAccountBusinessOpenRequest', Endpoint.ELECTRONIC_ACCOUNT_BUSINESS_OPEN, R.ElectronicAccountBusinessOpenResponse, WayCode.APPLY);
ElectronicAccountBusinessOpenRequest.prototype.setWayCode = function (v) { if (String(v) !== WayCode.APPLY) V.fail('way_code 固定为 APPLY'); this.way_code = WayCode.APPLY; return this; };
const ElectronicAccountBusinessChangeRequest = businessRequest('ElectronicAccountBusinessChangeRequest', Endpoint.ELECTRONIC_ACCOUNT_BUSINESS_CHANGE, R.ElectronicAccountBusinessChangeResponse, WayCode.APPLY);
ElectronicAccountBusinessChangeRequest.prototype.setWayCode = function (v) { if (String(v) !== WayCode.APPLY) V.fail('way_code 固定为 APPLY'); this.way_code = WayCode.APPLY; return this; };
const ElectronicAccountUserQueryRequest = fixedWayRequest('ElectronicAccountUserQueryRequest', Endpoint.ELECTRONIC_ACCOUNT_USER_QUERY, R.ElectronicAccountUserQueryResponse,
  { account_no: F.account_no, client_ip: F.client_ip }, WayCode.APPLY);
ElectronicAccountUserQueryRequest.prototype.validate = function () { this._validateFixed(); V.required('account_no', this.account_no, 32); V.ipv4('client_ip', this.client_ip); };
const ElectronicAccountSettlementQueryRequest = fixedWayRequest('ElectronicAccountSettlementQueryRequest', Endpoint.ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY, R.ElectronicAccountSettlementQueryResponse,
  { order_id: F.order_id, account_no: F.account_no, begin_date: F.begin_date, end_date: F.end_date, page_size: F.page_size, page_num: F.page_num }, WayCode.SETTLE_QUERY);
ElectronicAccountSettlementQueryRequest.prototype.validate = function () {
  this._validateFixed(); V.required('order_id', this.order_id, 30); V.required('account_no', this.account_no, 32);
  const a = V.date8('begin_date', this.begin_date); const b = V.date8('end_date', this.end_date);
  if (a > b) V.fail('begin_date 不能晚于 end_date'); if ((b - a) / 86400000 > 30) V.fail('日期跨度不能超过 30 天');
  if (this.page_size != null) V.integer('page_size', this.page_size, true); if (this.page_num != null) V.integer('page_num', this.page_num, true);
};
const ElectronicAccountBalanceWithdrawRequest = fixedWayRequest('ElectronicAccountBalanceWithdrawRequest', Endpoint.ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW, R.ElectronicAccountBalanceWithdrawResponse,
  { order_id: F.order_id, account_no: F.account_no, withdraw_amount: F.withdraw_amount }, WayCode.BALANCE_WITHDRAW);
ElectronicAccountBalanceWithdrawRequest.prototype.validate = function () {
  this._validateFixed(); V.required('order_id', this.order_id, 30); V.required('account_no', this.account_no, 32);
  if (!/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/.test(String(this.withdraw_amount || '')) || !/[1-9]/.test(String(this.withdraw_amount))) V.fail('withdraw_amount 必须是大于 0 且最多保留 2 位小数的元金额');
};
const ElectronicAccountSingleWithdrawQueryRequest = fixedWayRequest('ElectronicAccountSingleWithdrawQueryRequest', Endpoint.ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY, R.ElectronicAccountSingleWithdrawQueryResponse,
  { account_no: F.account_no, withdraw_date: F.withdraw_date, order_id: F.order_id }, WayCode.WITHDRAW_SINGLE_QUERY);
ElectronicAccountSingleWithdrawQueryRequest.prototype.validate = function () { this._validateFixed(); V.required('account_no', this.account_no, 32); V.date8('withdraw_date', this.withdraw_date); V.required('order_id', this.order_id, 30); };
const ElectronicAccountBatchWithdrawQueryRequest = fixedWayRequest('ElectronicAccountBatchWithdrawQueryRequest', Endpoint.ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY, R.ElectronicAccountBatchWithdrawQueryResponse,
  { account_no: F.account_no, begin_date: F.begin_date, end_date: F.end_date, page_size: F.page_size, page_num: F.page_num }, WayCode.WITHDRAW_QUERY);
ElectronicAccountBatchWithdrawQueryRequest.prototype.validate = function () {
  this._validateFixed(); V.required('account_no', this.account_no, 32); const a = V.date8('begin_date', this.begin_date); const b = V.date8('end_date', this.end_date);
  if (a > b) V.fail('begin_date 不能晚于 end_date'); if (this.page_size != null) V.integer('page_size', this.page_size, true); if (this.page_num != null) V.integer('page_num', this.page_num, true);
};

const exported = {
  UnifiedOrderRequest, QueryOrderRequest, RefundRequest, QueryRefundRequest, BalancePayRequest, BalanceQueryRequest,
  DelayTransConfirmRequest, DelayTransConfirmRefundRequest, DelayTransUnconfirmQueryRequest, DelayTransCreateTaskRequest,
  QuickPayApplyRequest, QuickPayBindApplyRequest, QuickPayBindConfirmRequest, QuickPayConfirmRequest, QuickPayUnbindRequest, QuickPaySmsCheckRequest,
  ElectronicAccountUserOpenRequest, ElectronicAccountBusinessOpenRequest, ElectronicAccountBusinessChangeRequest,
  ElectronicAccountUserQueryRequest, ElectronicAccountSettlementQueryRequest, ElectronicAccountBalanceWithdrawRequest,
  ElectronicAccountSingleWithdrawQueryRequest, ElectronicAccountBatchWithdrawQueryRequest,
};
module.exports = exported;
