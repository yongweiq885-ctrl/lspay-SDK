/**
 * Demo 通用初始化
 *
 *   - 调到 DEBUG=true
 *   - 注册 default 商户；设置了 LSPAY_B_* 时再注册 merch_b 商户
 *   - 仅供 Node.js 服务端示例使用，从服务端环境变量读取密钥
 */
const { LsPay, MerConfig } = require('../lspay');

let inited = false;

function env(key) {
  return (process.env[key] || '').trim();
}

function loadConfig(prefix) {
  const missing = ['MCH_NO', 'APP_ID', 'MD5_KEY']
    .map(name => prefix + name)
    .filter(key => !env(key));
  if (missing.length) {
    throw new Error('未配置商户信息，请先配置环境变量 ' + missing.join('、'));
  }
  return new MerConfig({
    mchNo: env(prefix + 'MCH_NO'),
    appId: env(prefix + 'APP_ID'),
    md5Key: env(prefix + 'MD5_KEY'),
    notifyUrl: env(prefix + 'NOTIFY_URL'),
    returnUrl: env(prefix + 'RETURN_URL'),
  });
}

function init() {
  if (inited) return;

  LsPay.DEBUG     = true;
  LsPay.PROD_MODE = true;
  LsPay.GATEWAY   = 'https://api.lspay.tech/';

  LsPay.init('default', loadConfig('LSPAY_'));
  if (['MCH_NO', 'APP_ID', 'MD5_KEY'].some(name => env('LSPAY_B_' + name))) {
    LsPay.init('merch_b', loadConfig('LSPAY_B_'));
  }
  inited = true;
}

module.exports = { init };
