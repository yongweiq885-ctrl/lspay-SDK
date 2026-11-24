using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Net;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace LsPaySdk;

public class LsPayException : Exception
{
    public LsPayException(string message, Exception? inner = null) : base(message, inner) { }
}

public sealed class LsPayConfigException : LsPayException
{
    public LsPayConfigException(string message) : base(message) { }
}

public sealed class LsPayValidationException : LsPayException
{
    public LsPayValidationException(string message) : base(message) { }
}

public sealed class LsPayProtocolException : LsPayException
{
    public LsPayProtocolException(string message, Exception? inner = null) : base(message, inner) { }
}

public sealed class LsPaySignException : LsPayException
{
    public LsPaySignException(string message) : base(message) { }
}

public sealed class LsPayNetworkException : LsPayException
{
    public int? HttpStatus { get; }
    public string? ResponseDetail { get; }

    public LsPayNetworkException(string message, int? httpStatus = null, string? responseDetail = null, Exception? inner = null)
        : base(message, inner)
    {
        HttpStatus = httpStatus;
        ResponseDetail = responseDetail;
    }
}

public sealed class LsPayBizException : LsPayException
{
    public string Code { get; }
    public string BizMessage { get; }
    public IReadOnlyDictionary<string, object?> Raw { get; }

    public LsPayBizException(string code, string message, IReadOnlyDictionary<string, object?>? raw = null)
        : base($"[LsPay {code}] {message}")
    {
        Code = code;
        BizMessage = message;
        Raw = raw ?? new Dictionary<string, object?>();
    }
}

public sealed class MerchantConfig
{
    public string MchNo { get; set; } = "";
    public string AppId { get; set; } = "";
    public string Md5Key { get; set; } = "";
    public string? NotifyUrl { get; set; }
    public string? ReturnUrl { get; set; }

    internal void Validate()
    {
        Guard.Required(MchNo, "mch_no", 30);
        Guard.Required(AppId, "app_id", 24);
        Guard.Required(Md5Key, "md5_key");
        Guard.OptionalUrl(NotifyUrl, "notify_url", 128);
        Guard.OptionalUrl(ReturnUrl, "return_url", 128);
    }
}

public sealed class LsPayOptions
{
    public const string ProductionBaseUrl = "https://api.lspay.tech";
    public const string SandboxBaseUrl = "https://sandbox.lspay.tech";
    private readonly ConcurrentDictionary<string, MerchantConfig> _merchants =
        new ConcurrentDictionary<string, MerchantConfig>(StringComparer.Ordinal);

    public bool Production { get; set; } = true;
    public string? BaseUrl { get; set; }
    public TimeSpan Timeout { get; set; } = TimeSpan.FromSeconds(30);
    public string UserAgent { get; set; } = "LsPaySdk-dotnet/3.0.0";
    public IWebProxy? Proxy { get; set; }
    public Action<string>? Log { get; set; }

    public LsPayOptions AddMerchant(string key, MerchantConfig config)
    {
        if (string.IsNullOrWhiteSpace(key)) throw new LsPayConfigException("merchant key 不能为空");
        if (config == null) throw new ArgumentNullException(nameof(config));
        config.Validate();
        _merchants[key] = config;
        return this;
    }

    public MerchantConfig GetMerchant(string key = "default")
    {
        if (!_merchants.TryGetValue(key, out var value))
            throw new LsPayConfigException($"商户配置不存在: {key}");
        return value;
    }

    public IReadOnlyCollection<string> MerchantKeys => _merchants.Keys.ToArray();
    internal string EffectiveBaseUrl => (BaseUrl ?? (Production ? ProductionBaseUrl : SandboxBaseUrl)).TrimEnd('/');
}

public static class LsPaySigner
{
    public static string Sign(IEnumerable<KeyValuePair<string, object?>> values, string secret)
    {
        var items = values
            .Where(x => x.Key != "sign" && x.Value != null && ConvertValue(x.Value) != "")
            .OrderBy(x => x.Key, StringComparer.Ordinal)
            .Select(x => x.Key + "=" + ConvertValue(x.Value));
        var source = string.Join("&", items.Concat(new[] { "key=" + (secret ?? "") }));
        using (var md5 = MD5.Create())
            return BitConverter.ToString(md5.ComputeHash(Encoding.UTF8.GetBytes(source))).Replace("-", "");
    }

    public static bool Verify(IReadOnlyDictionary<string, string> values, string secret)
    {
        if (!values.TryGetValue("sign", out var sign) || sign.Length != 32) return false;
        var expected = Sign(values.Select(x => new KeyValuePair<string, object?>(x.Key, x.Value)), secret);
        return FixedTimeEquals(expected.ToUpperInvariant(), sign.ToUpperInvariant());
    }

    public static long ToFen(string yuan)
    {
        if (!decimal.TryParse(yuan, NumberStyles.Number, CultureInfo.InvariantCulture, out var value))
            throw new ArgumentException("非法金额: " + yuan, nameof(yuan));
        return decimal.ToInt64(decimal.Truncate(value * 100m));
    }

    public static string ToYuan(long fen) => (fen / 100m).ToString("0.00", CultureInfo.InvariantCulture);

    private static string ConvertValue(object? value)
    {
        if (value == null) return "";
        if (value is string text) return text;
        if (value is bool boolean) return boolean ? "1" : "";
        if (value is Enum e) return ProtocolValue.Of(e);
        if (value is JsonElement json) return json.GetRawText();
        if (value is System.Collections.IDictionary || (value is System.Collections.IEnumerable && !(value is string)))
            return JsonSerializer.Serialize(value, Json.Options);
        return Convert.ToString(value, CultureInfo.InvariantCulture) ?? "";
    }

    private static bool FixedTimeEquals(string left, string right)
    {
        if (left.Length != right.Length) return false;
        var diff = 0;
        for (var i = 0; i < left.Length; i++) diff |= left[i] ^ right[i];
        return diff == 0;
    }
}

internal static class Guard
{
    internal static void Required(string? value, string field, int max = int.MaxValue)
    {
        if (string.IsNullOrWhiteSpace(value)) throw new LsPayValidationException(field + " 必填");
        if (value!.Length > max) throw new LsPayValidationException($"{field} 长度不能超过 {max}");
    }

    internal static void Optional(string? value, string field, int max)
    {
        if (value != null && value.Length > max) throw new LsPayValidationException($"{field} 长度不能超过 {max}");
    }

    internal static void Positive(long? value, string field)
    {
        if (!value.HasValue || value.Value <= 0) throw new LsPayValidationException(field + " 必须是大于 0 的整数");
    }

    internal static void Ipv4(string? value, string field = "client_ip")
    {
        if (string.IsNullOrEmpty(value)) return;
        if (!IPAddress.TryParse(value, out var address) || address.AddressFamily != System.Net.Sockets.AddressFamily.InterNetwork)
            throw new LsPayValidationException(field + " 必须是合法 IPv4 地址");
    }

    internal static void OptionalUrl(string? value, string field, int max)
    {
        if (string.IsNullOrEmpty(value)) return;
        if (value!.Length > max || !Uri.TryCreate(value, UriKind.Absolute, out var uri) ||
            (uri.Scheme != Uri.UriSchemeHttp && uri.Scheme != Uri.UriSchemeHttps))
            throw new LsPayValidationException($"{field} 必须是长度不超过 {max} 的 HTTP(S) URL");
    }

    internal static DateTime Date(string? value, string field)
    {
        if (!DateTime.TryParseExact(value, "yyyyMMdd", CultureInfo.InvariantCulture, DateTimeStyles.None, out var result))
            throw new LsPayValidationException(field + " 必须是合法的 yyyyMMdd 格式日期");
        return result;
    }

    internal static Dictionary<string, object?> JsonObject(string? value, string field)
    {
        if (string.IsNullOrEmpty(value)) return new Dictionary<string, object?>();
        try
        {
            var result = JsonSerializer.Deserialize<Dictionary<string, object?>>(value!, Json.Options);
            return result ?? throw new LsPayValidationException(field + " 必须是 JSON 对象字符串");
        }
        catch (JsonException ex) { throw new LsPayValidationException(field + " 必须是合法 JSON 对象字符串: " + ex.Message); }
    }
}

internal static class Json
{
    internal static readonly JsonSerializerOptions Options = new JsonSerializerOptions
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        DictionaryKeyPolicy = null,
        WriteIndented = false,
        DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.WhenWritingNull
    };
}
