using System;
using System.Collections;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace LsPaySdk;

public sealed class LsPayClient : IDisposable
{
    private static readonly HashSet<string> Reserved = new HashSet<string>(new[] { "mch_no", "app_id", "req_time", "version", "sign_type", "sign" }, StringComparer.Ordinal);
    private readonly LsPayOptions _options;
    private readonly HttpClient _http;
    private readonly bool _ownsHttpClient;
    private readonly string _merchantKey;

    public LsPayClient(LsPayOptions options, string merchantKey = "default", HttpClient? httpClient = null)
    {
        _options = options ?? throw new ArgumentNullException(nameof(options));
        _merchantKey = merchantKey;
        if (httpClient != null)
        {
            _http = httpClient;
        }
        else
        {
            var handler = new HttpClientHandler();
            if (options.Proxy != null) { handler.Proxy = options.Proxy; handler.UseProxy = true; }
            _http = new HttpClient(handler, true);
            _ownsHttpClient = true;
        }
        _http.Timeout = options.Timeout;
        if (!_http.DefaultRequestHeaders.UserAgent.TryParseAdd(options.UserAgent))
            throw new LsPayConfigException("User-Agent 非法");
    }

    public Task<BalancePayResponse> PostAsync(BalancePayRequest request, CancellationToken cancellationToken = default) =>
        PostCoreAsync<BalancePayResponse>(request, cancellationToken);

    public Task<QuickPayApplyResponse> PostAsync(QuickPayApplyRequest request, CancellationToken cancellationToken = default) =>
        PostCoreAsync<QuickPayApplyResponse>(request, cancellationToken);

    public Task<TResponse> PostAsync<TResponse>(LsPayRequest<TResponse> request, CancellationToken cancellationToken = default)
        where TResponse : LsPayResponse, new() => PostCoreAsync<TResponse>(request, cancellationToken);

    /// <summary>生成 pay_data_type=cashier 的收银台表单，由用户浏览器提交。</summary>
    public CashierForm BuildCashierForm(UnifiedOrderRequest request)
    {
        if (request == null) throw new ArgumentNullException(nameof(request));
        if (!IsCashierOrder(request)) throw new LsPayValidationException("BuildCashierForm 仅用于 channel_extra.pay_data_type=cashier");
        var (url, form) = SignRequest(request);
        return new CashierForm(url, form);
    }

    private static bool IsCashierOrder(LsPayRequest request)
    {
        if (!(request is UnifiedOrderRequest order) || order.ChannelExtra == null) return false;
        return order.ChannelExtra.ToDictionary().TryGetValue("pay_data_type", out var value)
            && Convert.ToString(value, CultureInfo.InvariantCulture) == ProtocolValue.Of(PayDataType.Cashier);
    }

    private async Task<TResponse> PostCoreAsync<TResponse>(LsPayRequest request, CancellationToken cancellationToken)
        where TResponse : LsPayResponse, new()
    {
        if (request == null) throw new ArgumentNullException(nameof(request));
        if (IsCashierOrder(request))
            throw new LsPayValidationException("pay_data_type=cashier 时网关返回收银台页面，不能由服务端调用接口；请使用 LsPayClient.BuildCashierForm() 生成表单，由用户浏览器提交");
        var (url, form) = SignRequest(request);
        HttpResponseMessage response;
        try
        {
            using (var content = new FormUrlEncodedContent(form))
                response = await _http.PostAsync(url, content, cancellationToken).ConfigureAwait(false);
        }
        catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
        {
            throw new LsPayNetworkException("请求立收通超时");
        }
        catch (HttpRequestException ex)
        {
            throw new LsPayNetworkException("请求立收通失败: " + ex.Message, inner: ex);
        }

        using (response)
        {
            var body = await response.Content.ReadAsStringAsync().ConfigureAwait(false);
            if (!response.IsSuccessStatusCode)
            {
                var detail = TrySanitizeBody(body);
                throw new LsPayNetworkException($"立收通 HTTP 状态异常: {(int)response.StatusCode}", (int)response.StatusCode, detail);
            }
            JsonDocument document;
            try { document = JsonDocument.Parse(body); }
            catch (JsonException ex) { throw new LsPayProtocolException("立收通响应不是合法 JSON 对象，长度=" + body.Length, ex); }
            using (document)
            {
                if (document.RootElement.ValueKind != JsonValueKind.Object) throw new LsPayProtocolException("立收通响应不是合法 JSON 对象");
                Log("RESPONSE " + url + " " + JsonSerializer.Serialize(LogSanitizer.Sanitize(document.RootElement), Json.Options));
                var result = new TResponse();
                result.Initialize(document.RootElement);
                return result;
            }
        }
    }

    /// <summary>注入公共字段并签名，返回请求地址和可直接提交的表单字段。</summary>
    private (string Url, Dictionary<string, string> Form) SignRequest(LsPayRequest request)
    {
        var merchant = _options.GetMerchant(_merchantKey);
        var parameters = request.ToParameters();
        foreach (var field in Reserved)
            if (parameters.ContainsKey(field)) throw new LsPayValidationException(field + " 是 SDK 管理的公共参数，业务参数不允许覆盖");

        if (request.Endpoint == Endpoint.UnifiedOrder)
        {
            if (!parameters.ContainsKey("notify_url") && !string.IsNullOrEmpty(merchant.NotifyUrl)) parameters["notify_url"] = merchant.NotifyUrl;
            if (!parameters.ContainsKey("return_url") && !string.IsNullOrEmpty(merchant.ReturnUrl)) parameters["return_url"] = merchant.ReturnUrl;
        }

        var data = new Dictionary<string, object?>(StringComparer.Ordinal)
        {
            ["mch_no"] = merchant.MchNo,
            ["app_id"] = merchant.AppId,
            ["req_time"] = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds().ToString(CultureInfo.InvariantCulture),
            ["version"] = "1.0",
            ["sign_type"] = "MD5"
        };
        foreach (var pair in parameters.Where(x => x.Value != null && !(x.Value is string text && text == ""))) data[pair.Key] = pair.Value;
        data["sign"] = LsPaySigner.Sign(data, merchant.Md5Key);

        var url = _options.EffectiveBaseUrl + "/" + ProtocolValue.Of(request.Endpoint);
        Log("REQUEST " + url + " " + JsonSerializer.Serialize(LogSanitizer.Sanitize(data), Json.Options));
        var form = data.ToDictionary(x => x.Key, x => FormValue(x.Value), StringComparer.Ordinal);
        return (url, form);
    }

    private static string FormValue(object? value)
    {
        if (value == null) return "";
        if (value is string text) return text;
        if (value is Enum enumeration) return ProtocolValue.Of(enumeration);
        if (value is bool boolean) return boolean ? "1" : "";
        if (value is IDictionary || (value is IEnumerable && !(value is string))) return JsonSerializer.Serialize(value, Json.Options);
        return Convert.ToString(value, CultureInfo.InvariantCulture) ?? "";
    }

    private string TrySanitizeBody(string body)
    {
        try
        {
            using (var doc = JsonDocument.Parse(body))
                return JsonSerializer.Serialize(LogSanitizer.Sanitize(doc.RootElement), Json.Options);
        }
        catch (JsonException) { return "[响应正文已省略]"; }
    }

    private void Log(string text) { _options.Log?.Invoke(text); }
    public void Dispose() { if (_ownsHttpClient) _http.Dispose(); }
}

public static class LogSanitizer
{
    private static readonly HashSet<string> Sensitive = new HashSet<string>(new[]
    {
        "sign","md5_key","auth_code","openid","buyer_id","trans_in","trans_out","account_no","account_name",
        "account_alias","mch_user_id","card_id","card_name","card_no","cert_id","cert_begin_date","cert_end_date",
        "card_mp","vip_code","expiration","verify_code","channel_user","channel_card_id","name","cert_no","mobile_no",
        "email","reg_name","short_name","license_code","reg_detail","legal_name","legal_cert_no","contact_name",
        "contact_mobile","mp","pay_info"
    }, StringComparer.OrdinalIgnoreCase);

    public static object? Sanitize(object? value)
    {
        if (value is JsonElement element) return SanitizeElement(element);
        if (value is IDictionary<string, object?> dictionary)
        {
            var result = new Dictionary<string, object?>();
            foreach (var item in dictionary)
            {
                if (Sensitive.Contains(item.Key)) result[item.Key] = Mask(Convert.ToString(item.Value, CultureInfo.InvariantCulture) ?? "");
                else if (item.Key == "channel_extra" && item.Value is string json) result[item.Key] = SanitizeJson(json);
                else result[item.Key] = Sanitize(item.Value);
            }
            return result;
        }
        if (value is IEnumerable sequence && !(value is string))
        {
            var list = new List<object?>();
            foreach (var item in sequence) list.Add(Sanitize(item));
            return list;
        }
        return value;
    }

    private static object? SanitizeElement(JsonElement element)
    {
        if (element.ValueKind == JsonValueKind.Object)
        {
            var result = new Dictionary<string, object?>();
            foreach (var property in element.EnumerateObject())
                result[property.Name] = Sensitive.Contains(property.Name) ? Mask(LsPayResponse.ReadAny(element, property.Name) ?? "") : SanitizeElement(property.Value);
            return result;
        }
        if (element.ValueKind == JsonValueKind.Array) return element.EnumerateArray().Select(SanitizeElement).ToList();
        if (element.ValueKind == JsonValueKind.String) return element.GetString();
        if (element.ValueKind == JsonValueKind.Number && element.TryGetInt64(out var integer)) return integer;
        if (element.ValueKind == JsonValueKind.Number && element.TryGetDecimal(out var number)) return number;
        if (element.ValueKind == JsonValueKind.True || element.ValueKind == JsonValueKind.False) return element.GetBoolean();
        return null;
    }

    private static object SanitizeJson(string json)
    {
        try { using (var document = JsonDocument.Parse(json)) return SanitizeElement(document.RootElement) ?? ""; }
        catch (JsonException) { return "[无法解析的渠道参数]"; }
    }

    private static string Mask(string value) => value.Length <= 4 ? "****" : value.Substring(0, 2) + "****" + value.Substring(value.Length - 2);
}
