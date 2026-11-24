const loader = require('./loader');
const demo = require('./demoConstants');
const { LsPay, LsPayClient, WayCode, UnifiedOrderRequest, UnifiedOrderChannelExtra } = require('../lspay');

async function main() {
  loader.init();
  const req = new UnifiedOrderRequest()
    .setMchOrderNo(demo.genOrderNo())
    .setWayCode(WayCode.WX_LITE)
    .setAmount(demo.DEFAULT_AMOUNT_FEN)
    .setSubject(demo.DEFAULT_SUBJECT)
    .setBody(demo.DEFAULT_BODY)
    .setNotifyUrl(LsPay.getConfig('default').notifyUrl)
    .setChannelExtra(new UnifiedOrderChannelExtra().setOpenid('oUpF8uMuAJO_M2pxb1Q9zNjWeS6o'));
  const resp = await LsPayClient.execute(req);
  if (resp.isError()) {
    console.log('[WX_LITE] 失败:', resp.getErrorInfo());
    return;
  }
  let payInfo = (resp.getData() || {}).pay_info;
  if (typeof payInfo === 'string') payInfo = JSON.parse(payInfo);
  console.log('[WX_LITE] 调起参数:', payInfo);
}

if (require.main === module) main().catch(e => console.error(e));
module.exports = main;
