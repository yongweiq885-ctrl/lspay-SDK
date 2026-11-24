using LsPaySdk;

var mchNo = Environment.GetEnvironmentVariable("LSPAY_MCH_NO");
var appId = Environment.GetEnvironmentVariable("LSPAY_APP_ID");
var md5Key = Environment.GetEnvironmentVariable("LSPAY_MD5_KEY");
if (string.IsNullOrEmpty(mchNo) || string.IsNullOrEmpty(appId) || string.IsNullOrEmpty(md5Key))
{
    Console.WriteLine("请设置 LSPAY_MCH_NO、LSPAY_APP_ID、LSPAY_MD5_KEY 后重试。");
    return;
}

var options = new LsPayOptions { Production = false, Log = Console.WriteLine }
    .AddMerchant("default", new MerchantConfig
    {
        MchNo = mchNo,
        AppId = appId,
        Md5Key = md5Key,
        NotifyUrl = Environment.GetEnvironmentVariable("LSPAY_NOTIFY_URL")
    });

using var client = new LsPayClient(options);
var scene = args.Length > 0 ? args[0] : "";
if (scene == "wx-lite")
{
    await WeChatScenarios.WxLite(client, options.GetMerchant().NotifyUrl);
    return;
}
if (scene == "wx-lite-jump")
{
    await WeChatScenarios.WxLiteJump(client, options.GetMerchant().NotifyUrl);
    return;
}
if (scene == "wx-lite-h5")
{
    await WeChatScenarios.WxLiteH5(client, null);
    return;
}
if (scene == "ali-lite-h5")
{
    await WeChatScenarios.AliLiteH5(client, null);
    return;
}

var request = new UnifiedOrderRequest
{
    MchOrderNo = "CS" + DateTimeOffset.UtcNow.ToUnixTimeMilliseconds(),
    WayCode = WayCode.ALI_QR,
    Amount = 1,
    Currency = "cny",
    Subject = "C# SDK 测试订单",
    Body = "C# SDK Demo",
    ClientIp = "127.0.0.1",
    ChannelExtra = new UnifiedOrderChannelExtra().PayData(PayDataType.CodeUrl)
};

try
{
    var response = await client.PostAsync(request);
    Console.WriteLine($"status={response.Status}, msg={response.Message}, payOrderId={response.PayOrderId}, payInfo={response.PayInfo}");
}
catch (LsPayException ex)
{
    Console.Error.WriteLine(ex.Message);
}
