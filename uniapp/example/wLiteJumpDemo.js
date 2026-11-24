const loader = require('./loader');
const demo = require('./demoConstants');
const { LsPay, LsPayClient, WayCode, UnifiedOrderRequest, UnifiedOrderChannelExtra } = require('../lspay');

async function main() {
  loader.init();
  const req = new UnifiedOrderRequest()
    .setMchOrderNo(demo.genOrderNo())
    .setWayCode(WayCode.WX_LITE_JUMP)
    .setAmount(demo.DEFAULT_AMOUNT_FEN)
    .setSubject(demo.DEFAULT_SUBJECT)
    .setBody(demo.DEFAULT_BODY)
    .setNotifyUrl(LsPay.getConfig('default').notifyUrl)
    .setChannelExtra(new UnifiedOrderChannelExtra().setOpenid('oUpF8uMuAJO_M2pxb1Q9zNjWeS6o'));
  const resp = await LsPayClient.execute(req);
  if (resp.isError()) {
    console.log('[WX_LITE_JUMP] 失败:', resp.getErrorInfo());
    return;
  }
  const data = resp.getData() || {};
  let payInfo = data.pay_info;
  if (typeof payInfo === 'string') payInfo = JSON.parse(payInfo);
  console.log('[WX_LITE_JUMP] 使用 app_id 和 path 跳转:', payInfo);
}

if (require.main === module) main().catch(e => console.error(e));
module.exports = main;
