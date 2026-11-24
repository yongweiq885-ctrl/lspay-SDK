const LsPay = require('./LsPay');
const BaseRequest = require('../request/BaseRequest');
const RequestFactory = require('../request/RequestFactory');
const { UnifiedOrderRequest } = require('../request/Requests');
const { LsPayValidationException } = require('../exceptions');

function escapeHtml(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/**
 * LsPayClient - 把 Request 对象转成 HTTP 调用
 */
const LsPayClient = {
  /**
   * @param {BaseRequest} request
   * @param {string} profile
   * @returns {Promise<LsPayResponse>}
   */
  async execute(request, profile = 'default') {
    if (!request) throw new Error('request 不能为空');
    if (!(request instanceof BaseRequest)) {
      if (!request || typeof request !== 'object' || !request.funcCode) {
        throw new LsPayValidationException('request 必须是 BaseRequest 对象或 {funcCode, params}');
      }
      const funcCode = request.funcCode;
      request = RequestFactory.fromArray(funcCode, request.params || {});
      if (!request) throw new LsPayValidationException(`不支持的 funcCode: ${funcCode}`);
    }
    const biz = this._bizParams(request, profile);
    return LsPay.post(request.getFunctionCode(), biz, profile, request.getResponseClass());
  },
  postRequest(request, profile = 'default') { return this.execute(request, profile); },

  /**
   * 生成 pay_data_type=cashier 的收银台表单，由用户浏览器把 fields 以 POST 提交到 action。
   * @returns {{action: string, method: string, fields: Object<string,string>, toHtml: function(): string}}
   */
  buildCashierForm(request, profile = 'default') {
    if (!(request instanceof UnifiedOrderRequest)) throw new LsPayValidationException('request 必须是 UnifiedOrderRequest');
    const biz = this._bizParams(request, profile);
    if (!LsPay.isCashierOrder(biz.channel_extra)) {
      throw new LsPayValidationException('buildCashierForm 仅用于 channel_extra.pay_data_type=cashier');
    }
    const signed = LsPay.buildSignedParams(biz, profile);
    const fields = {};
    Object.keys(signed).forEach(k => { fields[k] = String(signed[k]); });
    const action = LsPay._buildUrl(request.getFunctionCode());
    return {
      action,
      method: 'POST',
      fields,
      toHtml() {
        const inputs = Object.keys(fields)
          .map(k => `<input type="hidden" name="${escapeHtml(k)}" value="${escapeHtml(fields[k])}">`).join('');
        return `<form id="lspayCashierForm" method="POST" action="${escapeHtml(action)}">${inputs}</form>`
          + '<script>document.getElementById("lspayCashierForm").submit();</script>';
      },
    };
  },

  _bizParams(request, profile) {
    request.validate();
    const biz = request.toMap();
    const cfg = LsPay.getConfig(profile);
    if (!biz.notify_url && cfg.notifyUrl && Object.prototype.hasOwnProperty.call(request, 'notify_url')) biz.notify_url = cfg.notifyUrl;
    if (!biz.return_url && cfg.returnUrl && Object.prototype.hasOwnProperty.call(request, 'return_url')) biz.return_url = cfg.returnUrl;
    return biz;
  },
};

module.exports = LsPayClient;
