const loader = require('./loader');
const demo = require('./demoConstants');
const { LsPay, LsPayClient, WayCode, UnifiedOrderRequest, UnifiedOrderChannelExtra } = require('../lspay');

async function main() {
  loader.init();
  const req = new UnifiedOrderRequest()
    .setMchOrderNo(demo.genOrderNo())
    .setWayCode(WayCode.WX_LITE_H5)
    .setAmount(demo.DEFAULT_AMOUNT_FEN)
    .setSubject(demo.DEFAULT_SUBJECT)
    .setBody(demo.DEFAULT_BODY)
    .setClientIp('114.114.114.114')
    .setNotifyUrl(LsPay.getConfig('default').notifyUrl)
    .setReturnUrl('https://your.domain/pay_result.html')
    .setChannelExtra(new UnifiedOrderChannelExtra().setPayDataType('pay_url'));
  const resp = await LsPayClient.execute(req);
  if (resp.isError()) {
    console.log('[WX_LITE_H5] 失败:', resp.getErrorInfo());
    return;
  }
  const data = resp.getData() || {};
  let payInfo = data.pay_info;
  if (typeof payInfo === 'string') payInfo = JSON.parse(payInfo);
  if (payInfo && payInfo.path) {
    payInfo.path = payInfo.path + '?p=' + (data.pay_order_id || '') + '&s=app';
  }
  console.log('[WX_LITE_H5] App 使用 gh_id 和 path；H5 打开 scheme_code:', payInfo);
}

if (require.main === module) main().catch(e => console.error(e));
module.exports = main;
