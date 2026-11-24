const LsPay = require('../core/LsPay');
const LsPayTools = require('../core/LsPayTools');
const { LsPayValidationException, LsPaySignException } = require('../exceptions');

function requireText(data, field, max, allowEmpty) {
  if (!Object.prototype.hasOwnProperty.call(data, field)) throw new LsPayValidationException(`通知缺少 ${field}`);
  const value = String(data[field]);
  if (!allowEmpty && !value.trim()) throw new LsPayValidationException(`通知字段 ${field} 不能为空`);
  if (Array.from(value).length > max) throw new LsPayValidationException(`通知字段 ${field} 长度不能超过 ${max}`);
  return value;
}
function positive(data, field) {
  const v = requireText(data, field, 20);
  if (!/^\d+$/.test(v) || Number(v) <= 0) throw new LsPayValidationException(`通知字段 ${field} 必须是大于 0 的整数`);
  return Number(v);
}
class BaseNotify {
  constructor(data, config) {
    if (!data || typeof data !== 'object' || Array.isArray(data) || !Object.keys(data).length) throw new LsPayValidationException('通知参数不能为空');
    Object.keys(data).forEach(k => {
      if (!k || (data[k] && typeof data[k] === 'object')) throw new LsPayValidationException(`通知参数 ${k} 必须是标量值`);
    });
    if (!/^[a-fA-F0-9]{32}$/.test(String(data.sign || ''))) throw new LsPaySignException('通知缺少合法的 32 位 MD5 签名');
    if (!LsPayTools.verifySign(data, config.md5Key)) throw new LsPaySignException('通知签名验证失败');
    if (String(data.mch_no || '') !== String(config.mchNo) || String(data.app_id || '') !== String(config.appId)) {
      throw new LsPayValidationException('通知商户身份与 SDK 配置不匹配');
    }
    this.data = Object.assign({}, data);
  }
  getRaw() { return Object.assign({}, this.data); }
  value(k) { return this.data[k] == null ? null : String(this.data[k]); }
  int(k) { return /^\d+$/.test(this.value(k) || '') ? Number(this.value(k)) : null; }
  getNotifyType() { return this.value('notify_type'); } getEventType() { return this.value('event_type'); }
  getPayOrderId() { return this.value('pay_order_id'); } getMchNo() { return this.value('mch_no'); }
  getAppId() { return this.value('app_id'); } getWayCode() { return this.value('way_code'); }
  getCurrency() { return this.value('currency'); } getExtParam() { return this.value('ext_param'); }
  getAddTime() { return this.value('add_time'); } getSuccessTime() { return this.value('success_time'); }
  getReqTime() { return this.value('req_time'); } getSign() { return this.value('sign'); }
  getState() { return this.int('state'); }
  assertExpected(field, expected) {
    if (String(expected) !== this.value(field)) throw new LsPayValidationException(`通知字段 ${field} 与商户订单记录不一致`);
    return this;
  }
}
class PaymentNotify extends BaseNotify {
  constructor(data, config) {
    super(data, config);
    ['pay_order_id', 'mch_no', 'app_id', 'mch_order_no', 'way_code', 'currency', 'subject', 'body', 'add_time', 'req_time']
      .forEach(k => requireText(data, k, k === 'body' ? 256 : 64));
    positive(data, 'amount');
    if (![0, 1, 2, 3, 4, 5, 6].includes(Number(data.state))) throw new LsPayValidationException('通知字段 state 非法');
    if (data.event_type && !String(data.event_type).startsWith('payment')) throw new LsPayValidationException('通知事件类型不匹配');
    if (!/^\d{10}$/.test(String(data.add_time))) throw new LsPayValidationException('add_time 必须是 10 位秒级时间戳');
    if (data.success_time != null && data.success_time !== '' && !/^\d{10}$/.test(String(data.success_time))) throw new LsPayValidationException('success_time 必须是 10 位秒级时间戳');
  }
  getMchOrderNo() { return this.value('mch_order_no'); } getAmount() { return this.int('amount'); }
  getClientIp() { return this.value('client_ip'); } getSubject() { return this.value('subject'); }
  getBody() { return this.value('body'); } getChannelOrderNo() { return this.value('channel_order_no'); }
  getErrCode() { return this.value('err_code'); } getErrMsg() { return this.value('err_msg'); }
  isSucceeded() { return this.getState() === 2 && (!this.getEventType() || this.getEventType() === 'payment.succeeded'); }
  assertOrder(no, amount, currency = 'cny') { return this.assertExpected('mch_order_no', no).assertExpected('amount', amount).assertExpected('currency', String(currency).toLowerCase()); }
  getIdempotencyKey() { return `payment:${this.getPayOrderId()}:${this.getEventType() || this.getState()}`; }
}
class RefundNotify extends BaseNotify {
  constructor(data, config) {
    super(data, config);
    if (requireText(data, 'notify_type', 64) !== 'refund') throw new LsPayValidationException('通知字段 notify_type 必须是 refund');
    ['event_type', 'pay_order_id', 'mch_no', 'app_id', 'mch_refund_no', 'way_code', 'currency', 'add_time', 'success_time', 'req_time']
      .forEach(k => requireText(data, k, 64));
    const pay = positive(data, 'pay_amount'); const refund = positive(data, 'refund_amount');
    if (refund > pay) throw new LsPayValidationException('通知字段 refund_amount 不能大于 pay_amount');
  }
  getMchRefundNo() { return this.value('mch_refund_no'); } getPayAmount() { return this.int('pay_amount'); }
  getRefundAmount() { return this.int('refund_amount'); }
  isSucceeded() { return this.getState() === 2 && this.getEventType() === 'refund.succeeded'; }
  assertRefund(no, amount, currency = 'cny', payAmount) {
    this.assertExpected('mch_refund_no', no).assertExpected('refund_amount', amount).assertExpected('currency', String(currency).toLowerCase());
    return payAmount == null ? this : this.assertExpected('pay_amount', payAmount);
  }
  getIdempotencyKey() { return `refund:${this.getMchRefundNo()}:${this.getEventType()}`; }
}
class DelayTransTransferNotify extends BaseNotify {
  constructor(data, config) {
    super(data, config);
    ['task_id', 'mch_no', 'app_id', 'order_no', 'status', 'progress', 'account_no', 'account_name'].forEach(k => requireText(data, k, 128));
    const target = positive(data, 'target_amount');
    ['processed_amount', 'remaining_amount'].forEach(k => { if (!/^\d+$/.test(String(data[k]))) throw new LsPayValidationException(`通知字段 ${k} 必须是非负整数`); });
    if (!['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'PARTIAL'].includes(String(data.status))) throw new LsPayValidationException('通知字段 status 非法');
    if (Number(data.processed_amount) > target || Number(data.remaining_amount) > target) throw new LsPayValidationException('通知金额不能超过 target_amount');
  }
  getTaskId() { return this.value('task_id'); } getOrderNo() { return this.value('order_no'); }
  getTargetAmount() { return this.int('target_amount'); } getProcessedAmount() { return this.int('processed_amount'); }
  getRemainingAmount() { return this.int('remaining_amount'); } getTaskStatus() { return this.value('status'); }
  getProgress() { return Number(this.value('progress')); } getAccountNo() { return this.value('account_no'); }
  getAccountName() { return this.value('account_name'); } getRemark() { return this.value('remark'); }
  isSucceeded() { return this.getTaskStatus() === 'COMPLETED'; } isPartial() { return this.getTaskStatus() === 'PARTIAL'; }
  assertTask(no, amount) { return this.assertExpected('order_no', no).assertExpected('target_amount', amount); }
  getIdempotencyKey() { return `delaytrans-transfer:${this.getTaskId()}:${this.getTaskStatus()}:${this.getProcessedAmount()}`; }
}
const LsPayNotify = {
  SUCCESS_RESPONSE: 'ok', FAILURE_RESPONSE: 'fail',
  parse(params, profile = 'default') {
    if (params.notify_type === 'refund' || String(params.event_type || '').startsWith('refund.')) return this.parseRefund(params, profile);
    if (String(params.notify_type || '').startsWith('payment') || String(params.event_type || '').startsWith('payment.')) return this.parsePayment(params, profile);
    if (params.task_id && params.order_no && params.target_amount != null) return this.parseDelayTransTransfer(params, profile);
    throw new LsPayValidationException('无法识别通知类型');
  },
  parsePayment(p, profile = 'default') { return new PaymentNotify(p, LsPay.getConfig(profile)); },
  parseRefund(p, profile = 'default') { return new RefundNotify(p, LsPay.getConfig(profile)); },
  parseDelayTransTransfer(p, profile = 'default') { return new DelayTransTransferNotify(p, LsPay.getConfig(profile)); },
  successResponse() { return 'ok'; }, failureResponse() { return 'fail'; },
};
module.exports = { LsPayNotify, BaseNotify, PaymentNotify, RefundNotify, DelayTransTransferNotify };
