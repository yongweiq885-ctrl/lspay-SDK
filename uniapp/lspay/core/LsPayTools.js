const md5 = require('./md5');

/**
 * LsPayTools - 工具集（签名 / 时间 / 金额）
 */
const LsPayTools = {
  /**
   * 立收通官方签名算法（doc.lspay.tech）：
   *   1. 移除 sign 字段
   *   2. 过滤 null / undefined / 空字符串
   *   3. 按字段名 ASCII 升序
   *   4. 按 k=v&k=v 顺序拼接；对象/数组先 JSON.stringify
   *   5. 末尾追加 &key=应用密钥（md5_key）
   *   6. MD5 → 大写
   *
   * 注意：禁止用 encodeURIComponent 对 value 编码，与平台不一致。
   * @param {Object} data
   * @param {string} secret 商户应用密钥 md5_key（必填）
   */
  sign(data, secret) {
    if (!data) return '';
    const filtered = {};
    Object.keys(data).forEach(k => {
      if (k === 'sign') return;
      let v = data[k];
      if (v === null || v === undefined) return;
      if (typeof v === 'object') {
        v = JSON.stringify(v);
      }
      const sv = String(v);
      if (sv === '') return;
      filtered[k] = sv;
    });
    const keys = Object.keys(filtered).sort();
    let buf = '';
    keys.forEach(k => { buf += `${k}=${filtered[k]}&`; });
    buf += `key=${secret == null ? '' : secret}`;
    return md5(buf).toUpperCase();
  },

  verifySign(data, secret) {
    if (!data) return false;
    const got = data.sign;
    if (!got) return false;
    const expect = LsPayTools.sign(data, secret);
    return expect.toUpperCase() === String(got).toUpperCase();
  },

  msecTime() {
    return String(Date.now());
  },

  /** 元 → 分（避免浮点误差） */
  toFen(yuan) {
    const n = Number(yuan);
    if (!Number.isFinite(n)) throw new Error(`非法金额: ${yuan}`);
    return Math.round(n * 100);
  },

  /** 分 → 元（保留两位小数） */
  toYuan(fen) {
    const n = Number(fen);
    if (!Number.isFinite(n)) throw new Error(`非法金额: ${fen}`);
    return (n / 100).toFixed(2);
  },

  /** 随机 16 位字符串 */
  nonce(len = 16) {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let s = '';
    for (let i = 0; i < len; i++) s += chars.charAt(Math.floor(Math.random() * chars.length));
    return s;
  },
};

module.exports = LsPayTools;
