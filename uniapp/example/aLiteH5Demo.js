const loader = require('./loader');
const demo = require('./demoConstants');
const { LsPay, LsPayClient, WayCode, UnifiedOrderRequest } = require('../lspay');

async function main() {
  loader.init();
  const req = new UnifiedOrderRequest()
    .setMchOrderNo(demo.genOrderNo())
    .setWayCode(WayCode.ALI_LITE_H5)
    .setAmount(demo.DEFAULT_AMOUNT_FEN)
    .setSubject(demo.DEFAULT_SUBJECT)
    .setBody(demo.DEFAULT_BODY)
    .setClientIp('114.114.114.114')
    .setNotifyUrl(LsPay.getConfig('default').notifyUrl)
    .setReturnUrl('https://your.domain/pay_result.html')
    .setChannelExtra({ pay_data_type: 'pay_url' });
  const resp = await LsPayClient.execute(req);
  if (resp.isError()) {
    console.log('[ALI_LITE_H5] 失败:', resp.getErrorInfo());
    return;
  }
  console.log('[ALI_LITE_H5] App 打开 jump_url。app_schema 要带 :// :', resp.getData());
}

if (require.main === module) main().catch(e => console.error(e));
module.exports = main;
