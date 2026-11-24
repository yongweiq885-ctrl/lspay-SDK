# 立收通 UniApp / JavaScript SDK

支持 UniApp、微信小程序、浏览器和 Node.js（CommonJS）运行环境。

## 安全边界

**生产请求必须由业务服务端发起。不要在 UniApp、浏览器、小程序包或前端环境中保存 `md5_key`。**
前端应只请求业务方自己的后端，由后端完成参数校验、签名、网关调用和通知验签。客户端直连还可能受 CORS、域名白名单和包反编译影响。

SDK 保留多端 HTTP 适配，主要用于 Node 服务端、开发调试及兼容既有工程。日志会对签名、密钥、卡号、证件号、手机号和账户号等字段脱敏。

## 功能

- 支付四接口：统一下单、订单查询、申请退款、退款查询
- 余额：余额支付、余额查询
- 延时分账：交易确认、确认退款、可转余额查询、订单转账
- 快捷支付：支付申请、绑卡申请、绑卡确认、支付确认、解绑、短信校验
- 电子账户：用户开通、业务开通、业务变更、用户查询、结算查询、余额提现、单笔提现查询、批量提现查询
- 通知：支付、退款、延时分账转账；先验签和商户身份校验，再创建通知对象
- `RequestFactory` 数组兼容路由、各业务固定 `way_code`、类型化 Response 和 `channel_extra` 构造器

## 安装与引入

```js
const {
  LsPay, LsPayClient, MerConfig, WayCode, Endpoint,
  UnifiedOrderRequest, UnifiedOrderChannelExtra,
} = require('./lspay');
```

UniApp 项目也可从 `@/lspay` 引入；构建工具需要支持 CommonJS 互操作。

## 请求对象

```js
LsPay.init('server', new MerConfig({
  mchNo: process.env.LSPAY_MCH_NO,
  appId: process.env.LSPAY_APP_ID,
  md5Key: process.env.LSPAY_MD5_KEY,
  notifyUrl: 'https://merchant.example/lspay/notify',
}));

const request = new UnifiedOrderRequest()
  .setMchOrderNo(`PAY${Date.now()}`)
  .setWayCode(WayCode.ALI_QR)
  .setAmount(100)
  .setSubject('测试商品')
  .setBody('订单描述')
  .setChannelExtra(new UnifiedOrderChannelExtra().setPayDataType('code_img_url'));

const response = await LsPayClient.execute(request, 'server');
```

兼容 PHP SDK 的对象/数组路由：

```js
const response = await LsPayClient.postRequest({
  funcCode: Endpoint.QUERY_ORDER,
  params: { mch_order_no: 'PAY202610150001' },
});
```

已注册端点会经 `RequestFactory` 转换并执行同样的参数校验；未知字段作为扩展参数透传，但不能覆盖正式请求字段。

## channel_extra 构造器

统一下单和分账使用 `UnifiedOrderChannelExtra`、`DivisionReceiver`；余额支付使用 `BalancePayChannelExtra`；快捷支付使用 `QuickPayBindChannelExtra`、`QuickPayBindConfirmChannelExtra`、`QuickPayUnbindChannelExtra`；电子账户使用 `ElectronicAccountOpenChannelExtra`、用户/商户信息、结算卡、结算配置和提现配置构造器。

构造器可直接传给相应请求的 `setChannelExtra()`，SDK 会稳定序列化为 JSON 对象字符串。

## 通知验签

通知验签必须在服务端执行：

```js
const { LsPayNotify } = require('./lspay');

// body 为服务端解析出的表单字段对象
const notify = LsPayNotify.parse(body, 'server');
if (notify.isSucceeded()) {
  // 再用 assertOrder/assertRefund/assertTask 对照本地订单，并按 getIdempotencyKey() 去重
}
return LsPayNotify.successResponse(); // "ok"
```

`parsePayment`、`parseRefund`、`parseDelayTransTransfer` 可显式指定通知类型。验签使用收到的全部非空标量字段，未知字段也会保留并参与签名。

## 示例

`example/` 包含支付、退款、通知、余额与分账、快捷支付、电子账户示例。示例中的卡号、证件号、手机号和账户号均为占位符。

## 测试

```bash
npm run check   # 所有 JS 文件语法检查
npm test        # Node 无网络协议测试
npm run verify  # 依次执行上述两项
```

测试通过替换 SDK 内部传输函数捕获 URL、表单和响应路由，不连接真实网关。
