const loader = require('./loader');
const demo   = require('./demoConstants');
const { LsPayClient, RefundRequest } = require('../lspay');

async function main() {
  loader.init();
  const req = new RefundRequest()
    .setMchRefundNo(demo.genRefundNo())
    .setMchOrderNo('JT1700000000000')
    .setRefundAmount(1)
    .setPayAmount(1)
    .setRefundReason('用户申请退款');
  const resp = await LsPayClient.execute(req);
  if (resp.isError()) {
    console.log('退款失败:', resp.getErrorInfo());
    return;
  }
  console.log('退款受理:', resp.getData());
}

if (require.main === module) main().catch(e => console.error(e));
module.exports = main;
