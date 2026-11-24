/**
 * 商户配置对象
 */
class MerConfig {
  constructor(opts = {}) {
    this.mchNo     = opts.mchNo     || opts.mch_no     || '';
    this.appId     = opts.appId     || opts.app_id     || '';
    this.md5Key    = opts.md5Key    || opts.md5_key    || '';
    this.notifyUrl = opts.notifyUrl || opts.notify_url || '';
    this.returnUrl = opts.returnUrl || opts.return_url || '';
  }
}

module.exports = MerConfig;
