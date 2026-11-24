const loader = require('./loader');
const demo   = require('./demoConstants');
const { LsPay, LsPayClient, WayCode, UnifiedOrderRequest } = require('../lspay');

async function main() {
  loader.init();

  const req = new UnifiedOrderRequest()
    .setMchOrderNo(demo.genOrderNo())
    .setWayCode(WayCode.ALI_QR)
    .setAmount(demo.DEFAULT_AMOUNT_FEN)
    .setSubject(demo.DEFAULT_SUBJECT)
    .setBody(demo.DEFAULT_BODY)
    .setNotifyUrl(LsPay.getConfig('default').notifyUrl)
    .setClientIp('127.0.0.1')
    .setChannelExtra({ pay_data_type: 'pay_url' });

  const resp = await LsPayClient.execute(req);
  if (resp.isError()) {
    console.log('下单失败:', resp.getErrorInfo());
    return;
  }
  console.log('下单成功:', resp.getData());
}

if (require.main === module) main().catch(e => console.error(e));
module.exports = main;
