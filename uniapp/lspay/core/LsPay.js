const MerConfig = require('../config');
const { LsPayConfigException, LsPayNetworkException, LsPayValidationException } = require('../exceptions');
const Endpoint = require('../enums/Endpoint');
const LsPayTools = require('./LsPayTools');
const LsPayResponse = require('./LsPayResponse');

/**
 * LsPay - SDK 总控（多商户 + HTTP 发起）
 *
 * 同时兼容：
 *   - UniApp / 小程序  (uni.request / wx.request)
 *   - 浏览器          (fetch)
 *   - Node.js          (https module)
 */
const LsPay = {
  GATEWAY: 'https://api.lspay.tech/',
  // API 接口版本号 / 签名类型（写入每次请求的 version / sign_type 字段）
  API_VERSION: '1.0',
  SIGN_TYPE: 'MD5',

  CONNECT_MS: 10000,
  READ_MS: 15000,
  DEBUG: false,
  PROD_MODE: true,
  INSECURE: false,
  LOGGER: null,

  _configs: {},

  /**
   * 注册商户配置
   *   LsPay.init(cfg)
   *   LsPay.init('default', cfg)
   */
  init(profileOrCfg, cfg) {
    let profile;
    let realCfg;
    if (profileOrCfg instanceof MerConfig) {
      profile = 'default';
      realCfg = profileOrCfg;
    } else if (typeof profileOrCfg === 'string') {
      profile = profileOrCfg;
      realCfg = cfg instanceof MerConfig ? cfg : new MerConfig(cfg || {});
    } else if (profileOrCfg && typeof profileOrCfg === 'object') {
      profile = 'default';
      realCfg = new MerConfig(profileOrCfg);
    } else {
      throw new LsPayConfigException('参数非法：LsPay.init(profile, cfg) 或 LsPay.init(cfg)');
    }
    this._validate(realCfg);
    this._configs[profile] = realCfg;
  },

  getConfig(profile = 'default') {
    const cfg = this._configs[profile];
    if (!cfg) throw new LsPayConfigException(`未注册商户配置: ${profile}`);
    return cfg;
  },

  hasConfig(profile) { return Object.prototype.hasOwnProperty.call(this._configs, profile); },
  clear() { this._configs = {}; },

  _validate(cfg) {
    if (!cfg.mchNo)  throw new LsPayConfigException('mch_no 必填');
    if (!cfg.appId)  throw new LsPayConfigException('app_id 必填');
    if (!cfg.md5Key) throw new LsPayConfigException('md5_key 必填');
  },

  /**
   * 发起一次签名 + POST 调用
   *
   * 公共字段: mch_no / app_id / req_time / version / sign_type / sign
   * 传输格式: application/x-www-form-urlencoded;charset=utf-8
   *
   * @returns Promise<LsPayResponse>
   */
  async post(path, bizParams, profile = 'default', ResponseClass = LsPayResponse) {
    if (path === Endpoint.UNIFIED_ORDER && bizParams && this.isCashierOrder(bizParams.channel_extra)) {
      throw new LsPayValidationException('pay_data_type=cashier 时网关返回收银台页面，不能由服务端调用接口；'
        + '请使用 LsPayClient.buildCashierForm() 生成表单，由用户浏览器提交');
    }
    const all = this.buildSignedParams(bizParams, profile);

    const url = this._buildUrl(path);
    const body = this._buildFormBody(all);
    this._log(`>>> POST ${url} body=${this._redact(all)}`);

    const raw = await this._http(url, body);
    this._log(`<<< ${this._redact(raw)}`);
    return new ResponseClass(raw);
  },

  /** channel_extra.pay_data_type 是否为 cashier。 */
  isCashierOrder(channelExtra) {
    if (channelExtra === null || channelExtra === undefined || channelExtra === '') return false;
    let extra = channelExtra;
    if (typeof extra === 'string') {
      try { extra = JSON.parse(extra); } catch (e) { return false; }
    }
    return !!extra && typeof extra === 'object' && extra.pay_data_type === 'cashier';
  },

  /** 注入公共字段并签名，返回可直接提交的参数。 */
  buildSignedParams(bizParams, profile = 'default') {
    const cfg = this.getConfig(profile);
    const all = {
      mch_no:    cfg.mchNo,
      app_id:    cfg.appId,
      req_time:  LsPayTools.msecTime(),
      version:   this.API_VERSION,
      sign_type: this.SIGN_TYPE,
    };
    if (bizParams && typeof bizParams === 'object') {
      Object.keys(bizParams).forEach(k => {
        const v = bizParams[k];
        if (v === null || v === undefined) return;
        if (typeof v === 'string' && v === '') return;
        all[k] = v;
      });
    }

    all.sign = LsPayTools.sign(all, cfg.md5Key);
    return all;
  },

  /** 构造 application/x-www-form-urlencoded 请求体 */
  _buildFormBody(data) {
    const parts = [];
    Object.keys(data).forEach(k => {
      const v = data[k];
      if (v === null || v === undefined) return;
      parts.push(`${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`);
    });
    return parts.join('&');
  },

  _buildUrl(path) {
    const base = this.GATEWAY.endsWith('/') ? this.GATEWAY : this.GATEWAY + '/';
    const tail = path.startsWith('/') ? path.slice(1) : path;
    return base + tail;
  },

  _log(line) {
    if (!this.DEBUG && !this.LOGGER) return;
    if (typeof this.LOGGER === 'function') this.LOGGER(line);
    if (this.DEBUG && typeof console !== 'undefined') console.log('[LsPay]', line);
  },

  _redact(value) {
    const sensitive = /(^|_)(sign|key|secret|token|card_no|card_id|cert_no|cert_id|mobile|mobile_no|card_mp|account_no)$/i;
    let data = value;
    if (typeof value === 'string') {
      try { data = JSON.parse(value); } catch (_) {
        return value
          .replace(/((?:sign|key|secret|token|card_no|card_id|cert_no|cert_id|mobile|mobile_no|card_mp|account_no)=)[^&\s]*/gi, '$1***')
          .slice(0, 4096);
      }
    }
    const scrub = (v, key) => {
      if (sensitive.test(String(key || ''))) return '***';
      if (Array.isArray(v)) return v.map(x => scrub(x, ''));
      if (v && typeof v === 'object') return Object.keys(v).reduce((o, k) => { o[k] = scrub(v[k], k); return o; }, {});
      if (typeof v === 'string' && (/extra$/i.test(String(key || '')))) {
        try { return JSON.stringify(scrub(JSON.parse(v), '')); } catch (_) { return v; }
      }
      return v;
    };
    try { return JSON.stringify(scrub(data, '')).slice(0, 4096); } catch (_) { return '[unserializable]'; }
  },

  // ----- 多端 HTTP -----
  _http(url, body) {
    if (typeof uni !== 'undefined' && uni.request) {
      return this._httpUni(url, body);
    }
    if (typeof wx !== 'undefined' && wx.request) {
      return this._httpWx(url, body);
    }
    if (typeof fetch === 'function') {
      return this._httpFetch(url, body);
    }
    // Node.js
    return this._httpNode(url, body);
  },

  _httpUni(url, body) {
    return new Promise((resolve, reject) => {
      uni.request({
        url,
        method: 'POST',
        header: { 'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8' },
        data: body,
        success: (res) => {
          if (res.statusCode >= 200 && res.statusCode < 300) {
            resolve(typeof res.data === 'string' ? res.data : JSON.stringify(res.data));
          } else {
            reject(new LsPayNetworkException(`HTTP ${res.statusCode}`));
          }
        },
        fail: (err) => reject(new LsPayNetworkException(`uni.request 失败: ${err && err.errMsg}`)),
      });
    });
  },

  _httpWx(url, body) {
    return new Promise((resolve, reject) => {
      wx.request({
        url,
        method: 'POST',
        header: { 'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8' },
        data: body,
        success: (res) => {
          if (res.statusCode >= 200 && res.statusCode < 300) {
            resolve(typeof res.data === 'string' ? res.data : JSON.stringify(res.data));
          } else {
            reject(new LsPayNetworkException(`HTTP ${res.statusCode}`));
          }
        },
        fail: (err) => reject(new LsPayNetworkException(`wx.request 失败: ${err && err.errMsg}`)),
      });
    });
  },

  async _httpFetch(url, body) {
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8' },
        body,
      });
      const text = await res.text();
      if (!res.ok) throw new LsPayNetworkException(`HTTP ${res.status} body=${text}`);
      return text;
    } catch (e) {
      if (e instanceof LsPayNetworkException) throw e;
      throw new LsPayNetworkException(`fetch 失败: ${e.message}`);
    }
  },

  _httpNode(url, body) {
    return new Promise((resolve, reject) => {
      const u = new URL(url);
      const lib = u.protocol === 'https:' ? require('https') : require('http');
      const opts = {
        method: 'POST',
        hostname: u.hostname,
        port: u.port || (u.protocol === 'https:' ? 443 : 80),
        path: u.pathname + u.search,
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8',
          'Content-Length': Buffer.byteLength(body),
        },
      };
      if (this.INSECURE && u.protocol === 'https:') opts.rejectUnauthorized = false;
      const req = lib.request(opts, (res) => {
        const chunks = [];
        res.on('data', (c) => chunks.push(c));
        res.on('end', () => {
          const text = Buffer.concat(chunks).toString('utf8');
          if (res.statusCode >= 200 && res.statusCode < 300) {
            resolve(text);
          } else {
            reject(new LsPayNetworkException(`HTTP ${res.statusCode} body=${text}`));
          }
        });
      });
      req.on('error', (e) => reject(new LsPayNetworkException(`Node 请求失败: ${e.message}`)));
      req.setTimeout(Math.max(this.CONNECT_MS, this.READ_MS), () => {
        req.destroy(new LsPayNetworkException('请求超时'));
      });
      req.write(body);
      req.end();
    });
  },
};

module.exports = LsPay;
