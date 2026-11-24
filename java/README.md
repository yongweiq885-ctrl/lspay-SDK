# 立收通 (LSPay) Java SDK

JDK 8+。覆盖统一支付/退款、余额、延时分账、快捷支付、电子账户，支持 Map 兼容路由、强类型响应和通知验签。

```
tech.lspay.sdk/
├─ config/MerConfig.java
├─ exceptions/{LsPayException, LsPayConfigException, LsPayNetworkException,
│              LsPaySignException, LsPayBizException}.java
├─ enums/{WayCodeEnum, EndpointEnum, *StateEnum}.java
├─ core/{LsPay, LsPayClient, LsPayResponse, LsPayTools}.java
├─ request/{BaseRequest, RequestFactory, *Request, *ChannelExtra}.java
├─ response/{UnifiedOrderResponse, *Response}.java
├─ notify/{LsPayNotify, PaymentNotify, RefundNotify, DelayTransTransferNotify}.java
├─ internal/HttpUtil.java
└─ example/{Loader, DemoConstants, *RequestDemo, NotifyVerifyDemo,
            scenarios/*Demo}.java
```

## 1. 构建

```bash
cd java
mvn clean package
```

## 2. 最简用法

```java
import tech.lspay.sdk.config.MerConfig;
import tech.lspay.sdk.core.LsPay;
import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.core.LsPayResponse;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.request.UnifiedOrderRequest;

MerConfig cfg = new MerConfig();
cfg.mchNo  = "你的商户号";
cfg.appId  = "你的应用ID";
cfg.md5Key = "你的MD5密钥";
LsPay.init("default", cfg);

UnifiedOrderRequest req = new UnifiedOrderRequest()
        .setMchOrderNo("JT" + System.currentTimeMillis())
        .setWayCode(WayCodeEnum.ALI_QR)
        .setAmount(1)
        .setSubject("测试")
        .setBody("测试商品")
        .setChannelExtra("{\"pay_data_type\":\"code_url\"}");

LsPayResponse resp = LsPayClient.execute(req);
if (resp.isError()) {
    System.out.println(resp.getErrorInfo());
} else {
    System.out.println(resp.getData());
}
```

## 3. 多商户

```java
LsPay.init("default", cfgA);
LsPay.init("merch_b", cfgB);
LsPayResponse resp = LsPayClient.execute(req, "merch_b");
```

## 4. Demo 入口

| 类                                              | 场景               |
|------------------------------------------------|--------------------|
| `example.UnifiedOrderRequestDemo`              | 统一下单           |
| `example.QueryOrderRequestDemo`                | 订单查询           |
| `example.RefundRequestDemo`                    | 申请退款           |
| `example.QueryRefundRequestDemo`               | 退款查询           |
| `example.NotifyVerifyDemo`                     | 异步通知验签       |
| `example.ExtendedFeaturesDemo`                 | 余额/快捷/电子账户 |
| `example.scenarios.A_QRDemo`                   | 支付宝扫码 (ALI_QR) |
| `example.scenarios.A_LITE_H5Demo`              | 支付宝 H5/APP 收银台 (ALI_LITE_H5) |
| `example.scenarios.W_PUBDemo`                  | 微信 JSAPI (WX_PUB) |
| `example.scenarios.W_LITEDemo`                 | 微信小程序 (WX_LITE) |
| `example.scenarios.W_LITE_H5Demo`              | 微信 App / H5 跳转收银台 (WX_LITE_H5) |
| `example.scenarios.W_LITE_JUMPDemo`            | 自有小程序跳收银台 (WX_LITE_JUMP) |
| `example.scenarios.CUP_QRDemo`                 | 云闪付扫码 (CUP_QR) |

```bash
mvn exec:java -Dexec.mainClass=tech.lspay.sdk.example.UnifiedOrderRequestDemo
```

## 5. 全局配置

| 字段                | 默认值                          | 说明                          |
|---------------------|---------------------------------|-------------------------------|
| `LsPay.GATEWAY`     | `https://api.lspay.tech/`       | 网关基础地址                  |
| `LsPay.CONNECT_MS`  | `10000`                         | 连接超时（毫秒）              |
| `LsPay.READ_MS`     | `15000`                         | 读取超时（毫秒）              |
| `LsPay.DEBUG`       | `false`                         | 打印 `>>>` 请求 / `<<<` 响应  |
| `LsPay.PROD_MODE`   | `true`                          | 生产环境标记                  |
| `LsPay.INSECURE`    | `false`                         | 关闭 SSL 校验（仅开发环境）   |
| `LsPay.LOGGER`      | `null`                          | 自定义日志回调                |

## 6. 异常体系

```
LsPayException
├─ LsPayConfigException
├─ LsPayNetworkException
├─ LsPaySignException
└─ LsPayBizException(code, msg, raw)
```

参数不合规时抛 `IllegalArgumentException`，响应不是 JSON 时抛 fastjson 的 `JSONException`。

## 7. 完整接口范围

- 支付：统一下单、订单查询、退款、退款查询、余额支付、余额查询。
- 延时分账：确认、确认退款、可转余额查询、订单转账。
- 快捷支付：绑卡申请/确认、支付申请/确认、解绑、短信校验。
- 电子账户：用户开户、业务开通/变更、用户查询、结算记录、余额提现、单笔/批量提现查询。

所有固定业务码由请求对象默认设置并阻止改写。`channel_extra` 可传 JSON、`Map` 或对应构造器。

```java
LsPayResponse response = LsPayClient.execute(
        EndpointEnum.BALANCE_QUERY,
        new LinkedHashMap<String, Object>() {{
            put("order_id", "Q1001");
            put("account_no", "ACCOUNT");
            put("way_code", WayCodeEnum.BALANCE_QUERY);
        }});
```

已知端点的 Map 调用由 `RequestFactory` 转为强类型请求并执行同一套校验；未知字段作为扩展字段保留，SDK 公共签名字段禁止覆盖。

## 8. 通知验签与日志

```java
BaseNotify notify = LsPayNotify.parse(formFields, "default");
if (notify instanceof PaymentNotify && ((PaymentNotify) notify).isSucceeded()) {
    return LsPayNotify.successResponse(); // "ok"
}
```

通知使用收到的全部非空原始标量字段（包括未来新增字段）验签，并校验 `mch_no/app_id`。调试日志自动遮蔽签名、账户、银行卡、证件、手机号、验证码及 `channel_extra` 内同名敏感字段。生产环境不要开启 `DEBUG`，`INSECURE` 仅限本地联调。

## 9. 测试

```bash
mvn test
mvn verify
```

JUnit 4 协议测试覆盖端点路由、固定 `way_code`、嵌套 JSON、电子账户校验、通知原始字段验签及日志脱敏。
