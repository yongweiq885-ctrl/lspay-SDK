const loader = require('./loader');
const { LsPayClient, QueryRefundRequest } = require('../lspay');

async function main() {
  loader.init();
  const req = new QueryRefundRequest().setMchRefundNo('JR1700000000000');
  const resp = await LsPayClient.execute(req);
  if (resp.isError()) {
    console.log('退款查询失败:', resp.getErrorInfo());
    return;
  }
  console.log('退款状态:', resp.getData());
}

if (require.main === module) main().catch(e => console.error(e));
module.exports = main;
