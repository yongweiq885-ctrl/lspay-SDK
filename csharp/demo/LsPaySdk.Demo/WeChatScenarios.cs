using System.Text.Json;
using LsPaySdk;

public static class WeChatScenarios
{
    public static async Task WxLite(LsPayClient client, string? notifyUrl)
    {
        var response = await client.PostAsync(new UnifiedOrderRequest
        {
            MchOrderNo = "CS" + DateTimeOffset.UtcNow.ToUnixTimeMilliseconds(),
            WayCode = WayCode.WX_LITE,
            Amount = 1,
            Currency = "cny",
            Subject = "微信小程序测试",
            Body = "微信小程序测试",
            NotifyUrl = notifyUrl,
            ChannelExtra = new UnifiedOrderChannelExtra().OpenId("oUpF8uMuAJO_M2pxb1Q9zNjWeS6o")
        });
        Console.WriteLine("[WX_LITE] 调起参数: " + ParsePayInfo(response.PayInfo));
    }

    public static async Task AliLiteH5(LsPayClient client, string? notifyUrl)
    {
        var response = await client.PostAsync(new UnifiedOrderRequest
        {
            MchOrderNo = "CS" + DateTimeOffset.UtcNow.ToUnixTimeMilliseconds(),
            WayCode = WayCode.ALI_LITE_H5,
            Amount = 1,
            Currency = "cny",
            Subject = "支付宝 App/H5 测试",
            Body = "支付宝 App/H5 测试",
            ClientIp = "114.114.114.114",
            NotifyUrl = notifyUrl,
            ReturnUrl = "https://your.domain/pay_result.html"
        });
        Console.WriteLine("[ALI_LITE_H5] App 打开 jump_url。app_schema 要带 :// : " + response.PayInfo);
    }

    public static async Task WxLiteJump(LsPayClient client, string? notifyUrl)
    {
        var response = await client.PostAsync(new UnifiedOrderRequest
        {
            MchOrderNo = "CS" + DateTimeOffset.UtcNow.ToUnixTimeMilliseconds(),
            WayCode = WayCode.WX_LITE_JUMP,
            Amount = 1,
            Currency = "cny",
            Subject = "微信小程序跳收银台",
            Body = "微信小程序跳收银台",
            NotifyUrl = notifyUrl,
            ChannelExtra = new UnifiedOrderChannelExtra().OpenId("oUpF8uMuAJO_M2pxb1Q9zNjWeS6o")
        });
        Console.WriteLine("[WX_LITE_JUMP] 使用 app_id 和 path 跳转: " + ParsePayInfo(response.PayInfo));
    }

    public static async Task WxLiteH5(LsPayClient client, string? notifyUrl)
    {
        var response = await client.PostAsync(new UnifiedOrderRequest
        {
            MchOrderNo = "CS" + DateTimeOffset.UtcNow.ToUnixTimeMilliseconds(),
            WayCode = WayCode.WX_LITE_H5,
            Amount = 1,
            Currency = "cny",
            Subject = "微信 App/H5 测试",
            Body = "微信 App/H5 测试",
            ClientIp = "114.114.114.114",
            NotifyUrl = notifyUrl,
            ReturnUrl = "https://your.domain/pay_result.html",
            ChannelExtra = new UnifiedOrderChannelExtra().PayData(PayDataType.PayUrl)
        });
        var payInfo = ParsePayInfo(response.PayInfo);
        if (payInfo.ValueKind == JsonValueKind.Object
            && payInfo.TryGetProperty("path", out var path)
            && path.ValueKind == JsonValueKind.String)
        {
            Console.WriteLine("[WX_LITE_H5] App path: " + path.GetString()
                + "?p=" + response.PayOrderId + "&s=app");
        }
        if (payInfo.ValueKind == JsonValueKind.Object && payInfo.TryGetProperty("scheme_code", out var scheme))
            Console.WriteLine("[WX_LITE_H5] H5 scheme_code: " + scheme);
        Console.WriteLine("[WX_LITE_H5] pay_info: " + payInfo);
    }

    static JsonElement ParsePayInfo(string? payInfo)
    {
        if (string.IsNullOrWhiteSpace(payInfo)) return default;
        using var document = JsonDocument.Parse(payInfo);
        return document.RootElement.Clone();
    }
}
