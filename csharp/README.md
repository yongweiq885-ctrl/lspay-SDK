# LsPaySdk for .NET

立收通 .NET/C# SDK：公共参数注入、MD5 签名、`application/x-www-form-urlencoded` 传输、强类型请求/响应、通知验签以及日志脱敏。

## 环境与安装

- SDK：`netstandard2.0`
- Demo / 自包含测试：`net8.0`

项目引用：

```xml
<ProjectReference Include="path/to/csharp/src/LsPaySdk/LsPaySdk.csproj" />
```

发布 NuGet 包：

```powershell
dotnet pack src/LsPaySdk/LsPaySdk.csproj -c Release
```

## 配置与多商户

```csharp
var options = new LsPayOptions
{
    Production = true,
    Timeout = TimeSpan.FromSeconds(30),
    Log = line => logger.LogInformation("{Line}", line)
}
.AddMerchant("default", new MerchantConfig
{
    MchNo = "M...",
    AppId = "A...",
    Md5Key = "...",
    NotifyUrl = "https://merchant.example/notify",
    ReturnUrl = "https://merchant.example/return"
})
.AddMerchant("store-2", new MerchantConfig
{
    MchNo = "M2...",
    AppId = "A2...",
    Md5Key = "..."
});

using var client = new LsPayClient(options, "store-2");
```

生产、沙箱默认地址分别是 `https://api.lspay.tech` 和 `https://sandbox.lspay.tech`；可用 `BaseUrl` 覆盖。日志会递归遮蔽签名、账户、银行卡、证件、手机号、渠道用户标识及 `channel_extra` 内敏感字段。

## 下单、查询和退款

```csharp
var request = new UnifiedOrderRequest
{
    MchOrderNo = "ORDER100",
    WayCode = WayCode.WX_PUB,
    Amount = 100, // 分
    Currency = "cny",
    Subject = "商品",
    Body = "商品说明",
    ClientIp = "127.0.0.1",
    ChannelExtra = new UnifiedOrderChannelExtra()
        .OpenId("openid")
        .PayData(PayDataType.WxApp)
};
var result = await client.PostAsync(request);
if (result.IsPaid) { /* order_state=2 */ }

var order = await client.PostAsync(new QueryOrderRequest { MchOrderNo = "ORDER100" });
var refund = await client.PostAsync(new RefundRequest
{
    MchRefundNo = "REFUND100",
    MchOrderNo = "ORDER100",
    RefundAmount = 100,
    RefundReason = "用户申请"
});
var refundQuery = await client.PostAsync(new QueryRefundRequest { MchRefundNo = "REFUND100" });
```

同步响应的 `status=200` 仅代表接口受理；支付成功看 `OrderState.Paid`，退款完成看 `RefundState.Succeeded`。同步响应中的 `sign` 仅透传，不验签。

## 已覆盖接口

- 支付：统一下单、订单查询、申请退款、退款查询
- 余额：余额支付、余额查询
- 延时分账：交易确认、确认退款、可转余额查询、订单转账
- 快捷支付：绑卡申请、绑卡确认、支付申请、支付确认、解绑、短信校验
- 电子账户：用户开户、业务开通、业务变更、用户查询、结算记录、余额提现、单笔提现查询、批量提现查询
- 通知：支付、退款、延时分账订单转账

每个接口均有独立请求和响应类型。`Endpoint`、`WayCode`、订单/退款/电子账户/快捷支付/提现/转账任务状态均为枚举，线上值通过 `ProtocolValue.Of(...)` 获取。

## channel_extra 构造器

SDK 提供以下构造器并在发送前校验必填字段：

- `UnifiedOrderChannelExtra`、`DivisionChannelExtra`、`BalancePayChannelExtra`
- `QuickPayBindChannelExtra`、`QuickPayBindConfirmChannelExtra`、`QuickPayUnbindChannelExtra`
- `ElectronicAccountOpenChannelExtra`
- `ElectronicAccountBusinessChannelExtra`、`ElectronicAccountBusinessChangeChannelExtra`
- 电子账户用户、商户、结算卡、结算配置和提现配置子构造器

余额支付示例：

```csharp
var extra = new BalancePayChannelExtra()
    .TransOut("付款电子账户");
extra.AddReceiver(new DivisionReceiver("收款电子账户", 100, "订单分账"));

var response = await client.PostAsync(new BalancePayRequest
{
    MchOrderNo = "BAL100",
    Amount = 100,
    Subject = "余额支付",
    Body = "余额支付",
    ChannelExtra = extra
});
```

## 通知验签

Web 框架收到 multipart/form-data 后，把所有原始表单字段转换为 `IReadOnlyDictionary<string,string>`。不得先删除未知字段；未知非空字段也参与签名。

```csharp
var config = options.GetMerchant("default");
BaseNotify notify = LsPayNotify.Parse(form, config); // 构造时已验签及核对 mch_no/app_id

if (notify is PaymentNotify payment)
{
    payment.AssertOrder(localOrderNo, localAmountFen, "cny");
    if (payment.IsSucceeded)
    {
        // 用 payment.IdempotencyKey 做幂等处理
    }
}
return LsPayNotify.SuccessResponse; // 必须精确返回 ok；失败返回 fail
```

`LsPayNotify.ParsePayment`、`ParseRefund`、`ParseTransfer` 可显式解析支付、退款和转账通知。签名缺失、签名错误、商户不匹配和协议字段非法会分别抛出结构化异常。

## 异常

- `LsPayConfigException`：配置错误
- `LsPayValidationException`：请求或通知字段不符合契约
- `LsPaySignException`：通知签名错误
- `LsPayNetworkException`：连接、超时或非 2xx HTTP 状态
- `LsPayProtocolException`：响应不是合法 JSON 对象
- `LsPayBizException`：调用 `response.ThrowIfError()` 后的网关业务错误

## 构建、测试和 Demo

```powershell
dotnet build LsPaySdk.sln
dotnet run --project tests/LsPaySdk.Tests/LsPaySdk.Tests.csproj
dotnet run --project demo/LsPaySdk.Demo/LsPaySdk.Demo.csproj
```

测试项目是不依赖 xUnit/NUnit 的可执行断言程序，以降低离线环境依赖。Demo 从 `LSPAY_MCH_NO`、`LSPAY_APP_ID`、`LSPAY_MD5_KEY`、`LSPAY_NOTIFY_URL` 环境变量读取凭据。
