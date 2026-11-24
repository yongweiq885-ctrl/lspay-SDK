/**
 * BaseRequest - 所有 Request 对象的抽象基类
 *
 * 约定：
 *   - 子类通过 this.<field_name> 暴露字段（保持下划线命名，与 API 一致）
 *   - 字段属于实例自有属性 (Object.keys)
 *   - extendInfos 透传到顶层 body
 */
class BaseRequest {
  constructor() {
    this.extendInfos = {};
  }

  /** 子类必须实现：返回接口路径 */
  getFunctionCode() {
    throw new Error('子类必须实现 getFunctionCode()');
  }

  getResponseClass() {
    return require('../core/LsPayResponse');
  }

  validate() {}

  setExtendInfo(values) {
    if (!values || typeof values !== 'object' || Array.isArray(values)) {
      throw new (require('../exceptions').LsPayValidationException)('extendInfo 必须是对象');
    }
    const reserved = ['mch_no', 'app_id', 'req_time', 'version', 'sign_type', 'sign', 'funcCode', 'params'];
    const invalid = Object.keys(values).filter(k => reserved.includes(k));
    if (invalid.length) {
      throw new (require('../exceptions').LsPayValidationException)(`扩展参数不允许覆盖公共字段: ${invalid.join(', ')}`);
    }
    this.extendInfos = Object.assign({}, values);
    return this;
  }

  /**
   * 把自身字段拼成 Map：
   *   - 跳过 extendInfos
   *   - 跳过 null / undefined / ''
   *   - 跳过函数
   */
  toMap() {
    const out = {};
    Object.keys(this).forEach((k) => {
      if (k === 'extendInfos') return;
      const v = this[k];
      if (v === null || v === undefined) return;
      if (typeof v === 'function') return;
      if (typeof v === 'string' && v === '') return;
      out[k] = v;
    });
    if (this.extendInfos) {
      Object.keys(this.extendInfos).forEach((k) => {
        const v = this.extendInfos[k];
        if (v === null || v === undefined) return;
        if (Object.prototype.hasOwnProperty.call(out, k)) {
          throw new (require('../exceptions').LsPayValidationException)(`扩展参数不允许覆盖请求字段: ${k}`);
        }
        out[k] = v;
      });
    }
    return out;
  }
}

module.exports = BaseRequest;
