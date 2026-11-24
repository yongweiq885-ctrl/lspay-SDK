/**
 * 异步通知验签 Demo (Node.js / 服务端模拟)
 */
const loader = require('./loader');
const { LsPayNotify } = require('../lspay');

function main() {
  loader.init();

  // 实际服务端应使用框架解析 multipart/form-data 后得到的字段对象。
  const body = '{"notify_type":"payment","event_type":"payment.succeeded","mch_no":"M001","app_id":"APP001","pay_order_id":"P1","mch_order_no":"JT123","way_code":"ALI_QR","amount":"1","currency":"cny","state":"2","subject":"商品","body":"描述","add_time":"1660000000","success_time":"1660000001","req_time":"1660000000000","sign":"PUT_REAL_SIGN_HERE"}';
  const data = JSON.parse(body);

  const notify = LsPayNotify.parsePayment(data);
  notify.assertOrder('JT123', 1, 'cny');
  console.log('验签及订单核对通过:', notify.getIdempotencyKey());
  console.log('Reply:', LsPayNotify.successResponse());
}

if (require.main === module) main();
module.exports = main;
