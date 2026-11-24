/**
 * LsPayResponse - 响应封装
 */
class LsPayResponse {
  constructor(raw) {
    this.raw = raw || '';
    try { this._data = typeof raw === 'string' ? (raw ? JSON.parse(raw) : {}) : (raw || {}); }
    catch (e) { this._data = {}; }
    if (typeof this._data !== 'object' || this._data === null) {
      this._data = {};
    }
  }

  getRaw()  { return this.raw; }
  getMap()  { return this._data; }
  getStatus() {
    let value = this._data.status;
    if (value === null || value === undefined) value = this._data.code;
    if (value === null || value === undefined) value = this._data.retCode;
    return value === null || value === undefined ? '' : String(value);
  }
  /** 兼容旧代码，请优先使用 getStatus()。 */
  getCode() { return this.getStatus(); }
  getMsg() {
    const value = this._data.msg !== undefined ? this._data.msg : this._data.retMsg;
    return value === null || value === undefined ? '' : String(value);
  }
  /** 同步响应签名仅透传，不执行验签。 */
  getSign() {
    const value = this._data.sign;
    return value === null || value === undefined ? '' : String(value);
  }

  isError() {
    return !['200', '0', 'SUCCESS'].includes(this.getStatus().toUpperCase());
  }
  getErrorInfo() { return `[${this.getStatus()}] ${this.getMsg()}`; }

  getData() {
    let d = this._data.data;
    if (typeof d === 'string' && d) {
      try { d = JSON.parse(d); } catch (e) { d = null; }
    }
    return (d && typeof d === 'object') ? d : {};
  }

  dataValue(key) {
    const data = this.getData();
    if (Object.prototype.hasOwnProperty.call(data, key)) return data[key];
    return Object.prototype.hasOwnProperty.call(this._data, key) ? this._data[key] : null;
  }
  stringValue(key) {
    const value = this.dataValue(key);
    return value == null ? null : String(value);
  }
  integerValue(key) {
    const value = this.dataValue(key);
    return value != null && /^-?\d+$/.test(String(value)) ? Number(value) : null;
  }
}

module.exports = LsPayResponse;
