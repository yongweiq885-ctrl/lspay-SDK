const loader = require('./loader');
const { LsPayClient, QueryOrderRequest } = require('../lspay');

async function main() {
  loader.init();
  const req = new QueryOrderRequest().setMchOrderNo('JT1700000000000');
  const resp = await LsPayClient.execute(req);
  if (resp.isError()) {
    console.log('查询失败:', resp.getErrorInfo());
    return;
  }
  console.log('订单状态:', resp.getData());
}

if (require.main === module) main().catch(e => console.error(e));
module.exports = main;
