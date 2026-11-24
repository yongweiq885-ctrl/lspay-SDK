using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Net;
using System.Text.RegularExpressions;

namespace LsPaySdk;

public static class LsPayNotify
{
    public const string SuccessResponse = "ok";
    public const string FailureResponse = "fail";

    public static BaseNotify Parse(IReadOnlyDictionary<string, string> form, MerchantConfig config)
    {
        var notifyType = Value(form, "notify_type");
        var eventType = Value(form, "event_type");
        if (notifyType == "refund" || eventType.StartsWith("refund.", StringComparison.Ordinal)) return new RefundNotify(form, config);
        if (notifyType.StartsWith("payment", StringComparison.Ordinal) || eventType.StartsWith("payment.", StringComparison.Ordinal)) return new PaymentNotify(form, config);
        if (new[] { "task_id", "order_no", "target_amount", "processed_amount", "remaining_amount" }.All(form.ContainsKey)) return new DelayTransTransferNotify(form, config);
        throw new LsPayValidationException("无法根据 notify_type / event_type 识别通知类型");
    }

    public static PaymentNotify ParsePayment(IReadOnlyDictionary<string, string> form, MerchantConfig config) => new PaymentNotify(form, config);
    public static RefundNotify ParseRefund(IReadOnlyDictionary<string, string> form, MerchantConfig config) => new RefundNotify(form, config);
    public static DelayTransTransferNotify ParseTransfer(IReadOnlyDictionary<string, string> form, MerchantConfig config) => new DelayTransTransferNotify(form, config);
    private static string Value(IReadOnlyDictionary<string, string> values, string key) => values.TryGetValue(key, out var value) ? value : "";
}

public abstract class BaseNotify
{
    protected readonly IReadOnlyDictionary<string, string> Values;
    public IReadOnlyDictionary<string, string> Raw => Values;
    public string? NotifyType => Value("notify_type");
    public string? EventType => Value("event_type");
    public string? PayOrderId => Value("pay_order_id");
    public string? MchNo => Value("mch_no");
    public string? AppId => Value("app_id");
    public string? WayCode => Value("way_code");
    public string? Currency => Value("currency");
    public string? ExtParam => Value("ext_param");
    public string? AddTime => Value("add_time");
    public string? SuccessTime => Value("success_time");
    public string? ReqTime => Value("req_time");
    public string? Sign => Value("sign");
    public int? State => Integer("state");
    public abstract string IdempotencyKey { get; }

    protected BaseNotify(IReadOnlyDictionary<string, string> values, MerchantConfig config)
    {
        if (values == null || values.Count == 0) throw new LsPayValidationException("通知参数不能为空");
        config.Validate();
        if (!values.TryGetValue("sign", out var sign) || !Regex.IsMatch(sign, "^[A-Fa-f0-9]{32}$")) throw new LsPaySignException("通知缺少合法的 32 位 MD5 签名");
        if (!LsPaySigner.Verify(values, config.Md5Key)) throw new LsPaySignException("通知签名验证失败");
        if (!values.TryGetValue("mch_no", out var mchNo) || !values.TryGetValue("app_id", out var appId)) throw new LsPayValidationException("通知缺少 mch_no 或 app_id");
        if (!FixedEquals(config.MchNo, mchNo) || !FixedEquals(config.AppId, appId)) throw new LsPayValidationException("通知商户身份与 SDK 配置不匹配");
        Values = values.ToDictionary(x => x.Key, x => x.Value, StringComparer.Ordinal);
    }

    protected string? Value(string key) => Values.TryGetValue(key, out var value) ? value : null;
    protected int? Integer(string key) => int.TryParse(Value(key), NumberStyles.None, CultureInfo.InvariantCulture, out var value) ? value : (int?)null;
    protected long PositiveInteger(string field)
    {
        Required(field, 20);
        if (!long.TryParse(Value(field), NumberStyles.None, CultureInfo.InvariantCulture, out var value) || value <= 0) throw new LsPayValidationException("通知字段 " + field + " 必须是大于 0 的整数");
        return value;
    }
    protected string Required(string field, int max, bool allowEmpty = false)
    {
        if (!Values.TryGetValue(field, out var value) || (!allowEmpty && string.IsNullOrWhiteSpace(value))) throw new LsPayValidationException("通知缺少或字段为空: " + field);
        if (value.Length > max) throw new LsPayValidationException($"通知字段 {field} 长度不能超过 {max}");
        return value;
    }
    protected void AssertExpected(string field, string expected) { if (!FixedEquals(expected, Value(field) ?? "")) throw new LsPayValidationException("通知字段 " + field + " 与商户订单记录不一致"); }
    protected void AssertExpected(string field, long expected) { if (!long.TryParse(Value(field), out var actual) || expected != actual) throw new LsPayValidationException("通知字段 " + field + " 与商户订单记录不一致"); }
    private static bool FixedEquals(string left, string right) { if (left.Length != right.Length) return false; var diff = 0; for (var i = 0; i < left.Length; i++) diff |= left[i] ^ right[i]; return diff == 0; }
}

public sealed class PaymentNotify : BaseNotify
{
    public string MchOrderNo => Required("mch_order_no", 30);
    public long Amount => PositiveInteger("amount");
    public string? ClientIp => Value("client_ip");
    public string Subject => Required("subject", 64);
    public string Body => Required("body", 256);
    public string? ChannelOrderNo => Value("channel_order_no");
    public string? ErrorCode => Value("err_code");
    public string? ErrorMessage => Value("err_msg");
    public bool IsSucceeded => State == (int)OrderState.Paid && (EventType == null || EventType == "payment.succeeded");
    public override string IdempotencyKey => "payment:" + PayOrderId + ":" + (EventType ?? Convert.ToString(State, CultureInfo.InvariantCulture));

    public PaymentNotify(IReadOnlyDictionary<string, string> values, MerchantConfig config) : base(values, config)
    {
        Required("pay_order_id", 30); Required("mch_no", 30); Required("app_id", 24); Required("mch_order_no", 30);
        Required("way_code", 30); PositiveInteger("amount"); Required("currency", 3); Required("subject", 64); Required("body", 256);
        if (!State.HasValue || State < 0 || State > 6) throw new LsPayValidationException("通知字段 state 非法");
        if (!Regex.IsMatch(Required("add_time", 10), @"^\d{10}$")) throw new LsPayValidationException("add_time 必须是 10 位秒级时间戳");
        if (Values.TryGetValue("success_time", out var successTime) && !string.IsNullOrEmpty(successTime) && !Regex.IsMatch(successTime, @"^\d{10}$")) throw new LsPayValidationException("success_time 必须是 10 位秒级时间戳");
        if (!Regex.IsMatch(Required("req_time", 13), @"^\d{10,13}$")) throw new LsPayValidationException("通知 req_time 格式非法");
        if (ClientIp != null && (!IPAddress.TryParse(ClientIp, out var ip) || ip.AddressFamily != System.Net.Sockets.AddressFamily.InterNetwork)) throw new LsPayValidationException("通知字段 client_ip 必须是合法 IPv4 地址");
    }

    public PaymentNotify AssertOrder(string mchOrderNo, long amount, string currency = "cny") { AssertExpected("mch_order_no", mchOrderNo); AssertExpected("amount", amount); AssertExpected("currency", currency.ToLowerInvariant()); return this; }
}

public sealed class RefundNotify : BaseNotify
{
    public string MchRefundNo => Required("mch_refund_no", 30);
    public long PayAmount => PositiveInteger("pay_amount");
    public long RefundAmount => PositiveInteger("refund_amount");
    public bool IsSucceeded => State == (int)RefundState.Succeeded && EventType == "refund.succeeded";
    public override string IdempotencyKey => "refund:" + MchRefundNo + ":" + EventType;

    public RefundNotify(IReadOnlyDictionary<string, string> values, MerchantConfig config) : base(values, config)
    {
        if (Required("notify_type", 64) != "refund") throw new LsPayValidationException("通知字段 notify_type 必须是 refund");
        if (!Required("event_type", 64).StartsWith("refund.", StringComparison.Ordinal)) throw new LsPayValidationException("event_type 与退款通知不匹配");
        Required("pay_order_id", 30); Required("mch_no", 30); Required("app_id", 24); Required("mch_refund_no", 30); Required("way_code", 30);
        var pay = PositiveInteger("pay_amount"); var refund = PositiveInteger("refund_amount"); if (refund > pay) throw new LsPayValidationException("refund_amount 不能大于 pay_amount");
        Required("currency", 3); Required("ext_param", 512, true);
        ParseDateTime("add_time"); ParseDateTime("success_time");
        if (!Regex.IsMatch(Required("req_time", 10), @"^\d{10}$")) throw new LsPayValidationException("req_time 必须是 10 位数字");
        if (!State.HasValue || State < 0 || State > 4) throw new LsPayValidationException("通知字段 state 非法");
    }

    public RefundNotify AssertRefund(string mchRefundNo, long refundAmount, string currency = "cny", long? payAmount = null) { AssertExpected("mch_refund_no", mchRefundNo); AssertExpected("refund_amount", refundAmount); AssertExpected("currency", currency.ToLowerInvariant()); if (payAmount.HasValue) AssertExpected("pay_amount", payAmount.Value); return this; }
    private void ParseDateTime(string field) { if (!DateTime.TryParseExact(Required(field, 19), "yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture, DateTimeStyles.None, out _)) throw new LsPayValidationException("通知字段 " + field + " 必须是 yyyy-MM-dd HH:mm:ss 格式"); }
}

public sealed class DelayTransTransferNotify : BaseNotify
{
    public string TaskId => Required("task_id", 64);
    public string OrderNo => Required("order_no", 30);
    public long TargetAmount => PositiveInteger("target_amount");
    public long ProcessedAmount => NonNegative("processed_amount");
    public long RemainingAmount => NonNegative("remaining_amount");
    public DelayTransTaskStatus TaskStatus => ProtocolValue.Parse<DelayTransTaskStatus>(Required("status", 16));
    public decimal Progress => decimal.Parse(Required("progress", 8), CultureInfo.InvariantCulture);
    public string AccountNo => Required("account_no", 64);
    public string AccountName => Required("account_name", 128);
    public string? Remark => Value("remark");
    public bool IsSucceeded => TaskStatus == DelayTransTaskStatus.COMPLETED;
    public bool IsPartial => TaskStatus == DelayTransTaskStatus.PARTIAL;
    public override string IdempotencyKey => $"delaytrans-transfer:{TaskId}:{TaskStatus}:{ProcessedAmount}";

    public DelayTransTransferNotify(IReadOnlyDictionary<string, string> values, MerchantConfig config) : base(values, config)
    {
        Required("task_id", 64); Required("mch_no", 30); Required("app_id", 24); Required("order_no", 30);
        var target = PositiveInteger("target_amount"); var processed = NonNegative("processed_amount"); var remaining = NonNegative("remaining_amount");
        var status = ProtocolValue.Parse<DelayTransTaskStatus>(Required("status", 16));
        if (!decimal.TryParse(Required("progress", 8), NumberStyles.Number, CultureInfo.InvariantCulture, out var progress) || progress < 0 || progress > 100) throw new LsPayValidationException("progress 必须是 0-100 的数字");
        Required("account_no", 64); Required("account_name", 128);
        if (processed > target || remaining > target) throw new LsPayValidationException("通知金额不能超过 target_amount");
        if ((status == DelayTransTaskStatus.PENDING || status == DelayTransTaskStatus.FAILED) && (processed != 0 || remaining != target)) throw new LsPayValidationException("PENDING/FAILED 状态的通知金额关系非法");
        if ((status == DelayTransTaskStatus.PROCESSING || status == DelayTransTaskStatus.PARTIAL) && (processed <= 0 || processed >= target || remaining != target - processed)) throw new LsPayValidationException("PROCESSING/PARTIAL 状态的通知金额关系非法");
    }

    public DelayTransTransferNotify AssertTask(string orderNo, long targetAmount) { AssertExpected("order_no", orderNo); AssertExpected("target_amount", targetAmount); return this; }
    private long NonNegative(string field) { if (!long.TryParse(Required(field, 20), NumberStyles.None, CultureInfo.InvariantCulture, out var value) || value < 0) throw new LsPayValidationException(field + " 必须是非负整数"); return value; }
}
