using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Reflection;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;

namespace LsPaySdk;

public abstract class LsPayRequest
{
    [JsonIgnore] public abstract Endpoint Endpoint { get; }
    [JsonIgnore] public IDictionary<string, object?> ExtendInfo { get; } = new Dictionary<string, object?>(StringComparer.Ordinal);
    public virtual void Validate() { }

    public IDictionary<string, object?> ToParameters()
    {
        Validate();
        var values = new Dictionary<string, object?>(StringComparer.Ordinal);
        foreach (var property in GetType().GetProperties(BindingFlags.Instance | BindingFlags.Public))
        {
            if (property.GetCustomAttribute<JsonIgnoreAttribute>() != null || !property.CanRead) continue;
            var value = property.GetValue(this);
            if (value == null || (value is string text && text == "")) continue;
            var name = property.GetCustomAttribute<JsonPropertyNameAttribute>()?.Name ?? ToSnakeCase(property.Name);
            if (value is Enum enumeration) value = ProtocolValue.Of(enumeration);
            else if (value is ChannelExtra extra) value = extra.ToJson();
            values[name] = value;
        }
        foreach (var item in ExtendInfo)
        {
            if (values.ContainsKey(item.Key)) throw new LsPayValidationException("扩展参数不允许覆盖请求字段: " + item.Key);
            values[item.Key] = item.Value;
        }
        return values;
    }

    private static string ToSnakeCase(string name) =>
        Regex.Replace(name, "([a-z0-9])([A-Z])", "$1_$2").ToLowerInvariant();
}

public abstract class LsPayRequest<TResponse> : LsPayRequest where TResponse : LsPayResponse, new() { }

public class UnifiedOrderRequest : LsPayRequest<UnifiedOrderResponse>
{
    protected virtual bool AllowsBusinessWayCode => false;
    public override Endpoint Endpoint => Endpoint.UnifiedOrder;
    public string? MchOrderNo { get; set; }
    public WayCode? WayCode { get; set; }
    public long? Amount { get; set; }
    public string Currency { get; set; } = "cny";
    public string? Subject { get; set; }
    public string? Body { get; set; }
    public ChannelExtra? ChannelExtra { get; set; }
    public string? ClientIp { get; set; }
    public string? NotifyUrl { get; set; }
    public string? ReturnUrl { get; set; }
    public int? ExpiredTime { get; set; }
    public int? DivisionMode { get; set; }
    public string? ExtParam { get; set; }
    public int? PollingMode { get; set; }

    public override void Validate()
    {
        Guard.Required(MchOrderNo, "mch_order_no", 30);
        if (!WayCode.HasValue) throw new LsPayValidationException("way_code 必填");
        if (!AllowsBusinessWayCode && !WayCodeCatalog.IsPaymentMethod(WayCode.Value))
            throw new LsPayValidationException("way_code 不在统一下单支付方式清单中");
        Guard.Positive(Amount, "amount");
        if (!Regex.IsMatch(Currency ?? "", "^[A-Za-z]{3}$")) throw new LsPayValidationException("currency 必须是 3 位字母货币代码");
        Guard.Required(Subject, "subject", 64);
        Guard.Required(Body, "body", 256);
        Guard.Ipv4(ClientIp);
        Guard.OptionalUrl(NotifyUrl, "notify_url", 128);
        Guard.OptionalUrl(ReturnUrl, "return_url", 128);
        Guard.Optional(ExtParam, "ext_param", 512);
        if (ExpiredTime.HasValue && ExpiredTime <= 0) throw new LsPayValidationException("expired_time 必须是大于 0 的秒数");
        if (DivisionMode.HasValue && !new[] { 0, 1, 2 }.Contains(DivisionMode.Value)) throw new LsPayValidationException("division_mode 只允许 0、1、2");
        if (PollingMode.HasValue && !new[] { 0, 1, 2, 3 }.Contains(PollingMode.Value)) throw new LsPayValidationException("polling_mode 只允许 0、1、2、3");
        if (ChannelExtra == null) throw new LsPayValidationException("channel_extra 必填");
        var extra = ChannelExtra.ToDictionary();
        var required = new Dictionary<WayCode, string>
        {
            [LsPaySdk.WayCode.ALI_LITE] = "buyer_id", [LsPaySdk.WayCode.WX_LITE] = "openid",
            [LsPaySdk.WayCode.WX_BAR] = "auth_code", [LsPaySdk.WayCode.ALI_BAR] = "auth_code",
            [LsPaySdk.WayCode.YSF_BAR] = "auth_code"
        };
        extra.TryGetValue("pay_data_type", out var payDataType);
        if (string.IsNullOrWhiteSpace(Convert.ToString(payDataType)))
        {
            required[LsPaySdk.WayCode.ALI_PUB] = "buyer_id";
            required[LsPaySdk.WayCode.WX_PUB] = "openid";
        }
        if (required.TryGetValue(WayCode!.Value, out var field) && (!extra.TryGetValue(field, out var v) || string.IsNullOrWhiteSpace(Convert.ToString(v))))
            throw new LsPayValidationException($"way_code={WayCode} 时 channel_extra.{field} 必填");
        if (WayCode == LsPaySdk.WayCode.BANK_FAST)
            RequireExtra(extra, "channel_user", "channel_card_id");
        if ((DivisionMode == 1 || DivisionMode == 2) && (!extra.TryGetValue("div_list", out var list) || !(list is System.Collections.IEnumerable)))
            throw new LsPayValidationException("division_mode 为 1 或 2 时 channel_extra.div_list 必填且不能为空");
    }

    protected static void RequireExtra(IDictionary<string, object?> values, params string[] fields)
    {
        foreach (var field in fields)
            if (!values.TryGetValue(field, out var value) || string.IsNullOrWhiteSpace(Convert.ToString(value)))
                throw new LsPayValidationException("channel_extra." + field + " 必填");
    }
}

public sealed class QueryOrderRequest : LsPayRequest<QueryOrderResponse>
{
    public override Endpoint Endpoint => Endpoint.QueryOrder;
    public string? MchOrderNo { get; set; }
    public string? PayOrderId { get; set; }
    public override void Validate() { Guard.Optional(MchOrderNo, "mch_order_no", 30); Guard.Optional(PayOrderId, "pay_order_id", 30); if (string.IsNullOrEmpty(MchOrderNo) && string.IsNullOrEmpty(PayOrderId)) throw new LsPayValidationException("mch_order_no 与 pay_order_id 至少填写一个"); }
}

public sealed class RefundRequest : LsPayRequest<RefundResponse>
{
    public override Endpoint Endpoint => Endpoint.Refund;
    public string? MchRefundNo { get; set; }
    public string? MchOrderNo { get; set; }
    public string? PayOrderId { get; set; }
    public long? RefundAmount { get; set; }
    public string Currency { get; set; } = "cny";
    public string? RefundReason { get; set; }
    public string? ClientIp { get; set; }
    public string? NotifyUrl { get; set; }
    public ChannelExtra? ChannelExtra { get; set; }
    public string? ExtParam { get; set; }
    public override void Validate()
    {
        Guard.Required(MchRefundNo, "mch_refund_no", 30); Guard.Optional(MchOrderNo, "mch_order_no", 30); Guard.Optional(PayOrderId, "pay_order_id", 30);
        if (string.IsNullOrEmpty(MchOrderNo) && string.IsNullOrEmpty(PayOrderId)) throw new LsPayValidationException("mch_order_no 与 pay_order_id 至少填写一个");
        Guard.Positive(RefundAmount, "refund_amount"); Guard.Required(RefundReason, "refund_reason", 64); Guard.Ipv4(ClientIp); Guard.OptionalUrl(NotifyUrl, "notify_url", 128);
    }
}

public sealed class QueryRefundRequest : LsPayRequest<QueryRefundResponse>
{
    public override Endpoint Endpoint => Endpoint.QueryRefund;
    public string? MchRefundNo { get; set; }
    public string? OrderRefundId { get; set; }
    public override void Validate() { Guard.Optional(MchRefundNo, "mch_refund_no", 30); Guard.Optional(OrderRefundId, "order_refund_id", 30); if (string.IsNullOrEmpty(MchRefundNo) && string.IsNullOrEmpty(OrderRefundId)) throw new LsPayValidationException("mch_refund_no 与 order_refund_id 至少填写一个"); }
}

public sealed class BalancePayRequest : UnifiedOrderRequest
{
    protected override bool AllowsBusinessWayCode => true;
    public BalancePayRequest() { WayCode = LsPaySdk.WayCode.BALANCE_PAY; DivisionMode = 1; }
    public override void Validate() { base.Validate(); if (WayCode != LsPaySdk.WayCode.BALANCE_PAY || DivisionMode != 1) throw new LsPayValidationException("余额支付固定 way_code=BALANCE_PAY 且 division_mode=1"); var values = ChannelExtra?.ToDictionary() ?? new Dictionary<string, object?>(); RequireExtra(values, "trans_out", "div_list"); }
}

public sealed class BalanceQueryRequest : LsPayRequest<BalanceQueryResponse>
{
    public override Endpoint Endpoint => Endpoint.BalanceQuery;
    public string? OrderId { get; set; }
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.BALANCE_QUERY;
    public string? ClientIp { get; set; }
    public string? AccountNo { get; set; }
    public override void Validate() { Guard.Required(OrderId, "order_id", 30); if (WayCode != LsPaySdk.WayCode.BALANCE_QUERY) throw new LsPayValidationException("way_code 固定为 BALANCE_QUERY"); Guard.Required(AccountNo, "account_no", 256); Guard.Ipv4(ClientIp); }
}

public sealed class DelayTransConfirmRequest : LsPayRequest<DelayTransConfirmResponse>
{
    public override Endpoint Endpoint => Endpoint.DelayTransConfirm;
    public string? PayOrderId { get; set; }
    public string? MchOrderNo { get; set; }
    public string? OrderId { get; set; }
    public ChannelExtra? ChannelExtra { get; set; }
    public override void Validate() { Guard.Optional(PayOrderId, "pay_order_id", 30); Guard.Optional(MchOrderNo, "mch_order_no", 30); if (string.IsNullOrEmpty(PayOrderId) && string.IsNullOrEmpty(MchOrderNo)) throw new LsPayValidationException("pay_order_id 与 mch_order_no 至少填写一个"); Guard.Required(OrderId, "order_id", 30); }
}

public sealed class DelayTransConfirmRefundRequest : LsPayRequest<DelayTransConfirmRefundResponse>
{
    public override Endpoint Endpoint => Endpoint.DelayTransConfirmRefund;
    public string? ConfirmOrderId { get; set; }
    public string? ConfirmRefundNo { get; set; }
    public ChannelExtra? ChannelExtra { get; set; }
    public override void Validate() { Guard.Required(ConfirmOrderId, "confirm_order_id", 30); Guard.Required(ConfirmRefundNo, "confirm_refund_no", 30); }
}

public sealed class DelayTransUnconfirmQueryRequest : LsPayRequest<DelayTransUnconfirmQueryResponse> { public override Endpoint Endpoint => Endpoint.DelayTransUnconfirmQuery; }

public sealed class DelayTransCreateTaskRequest : LsPayRequest<DelayTransCreateTaskResponse>
{
    public override Endpoint Endpoint => Endpoint.DelayTransCreateTask;
    public string? AccountNo { get; set; }
    public string? AccountName { get; set; }
    public string? OrderNo { get; set; }
    public long? Amount { get; set; }
    public string? NotifyUrl { get; set; }
    public string? Remark { get; set; }
    public override void Validate() { Guard.Required(AccountNo, "account_no", 30); Guard.Required(AccountName, "account_name", 30); Guard.Required(OrderNo, "order_no", 30); Guard.Positive(Amount, "amount"); Guard.OptionalUrl(NotifyUrl, "notify_url", 64); Guard.Optional(Remark, "remark", 256); }
}

public sealed class QuickPayBindApplyRequest : LsPayRequest<QuickPayBindApplyResponse>
{
    public override Endpoint Endpoint => Endpoint.QuickPayBindApply;
    public string? OrderId { get; set; }
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.BANK_FAST;
    public string? ClientIp { get; set; }
    public QuickPayBindChannelExtra? ChannelExtra { get; set; }
    public override void Validate() { Guard.Required(OrderId, "order_id", 30); if (WayCode != LsPaySdk.WayCode.BANK_FAST) throw new LsPayValidationException("way_code 固定为 BANK_FAST"); Guard.Ipv4(ClientIp); if (ChannelExtra == null) throw new LsPayValidationException("channel_extra 必填"); ChannelExtra.Validate(); }
}

public sealed class QuickPayBindConfirmRequest : LsPayRequest<QuickPayBindConfirmResponse>
{
    public override Endpoint Endpoint => Endpoint.QuickPayBindConfirm;
    public string? OrderId { get; set; }
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.BANK_FAST;
    public string? ClientIp { get; set; }
    public QuickPayBindConfirmChannelExtra? ChannelExtra { get; set; }
    public string? VerifyCode { get; set; }
    public override void Validate() { Guard.Required(OrderId, "order_id", 30); Guard.Ipv4(ClientIp); if (ChannelExtra == null) throw new LsPayValidationException("channel_extra 必填"); ChannelExtra.Validate(); if (!Regex.IsMatch(VerifyCode ?? "", @"^\d{3,8}$")) throw new LsPayValidationException("verify_code 必须是 3-8 位数字"); }
}

public sealed class QuickPayApplyRequest : UnifiedOrderRequest
{
    public QuickPayApplyRequest() { WayCode = LsPaySdk.WayCode.BANK_FAST; }
    public override void Validate() { if (WayCode != LsPaySdk.WayCode.BANK_FAST) throw new LsPayValidationException("way_code 固定为 BANK_FAST"); base.Validate(); }
}

public class QuickPayConfirmRequest : LsPayRequest<QuickPayConfirmResponse>
{
    public override Endpoint Endpoint => Endpoint.QuickPayConfirm;
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.BANK_FAST;
    public string? ClientIp { get; set; }
    public string? PayOrderId { get; set; }
    public string? MchOrderNo { get; set; }
    public string? VerifyCode { get; set; }
    public override void Validate() { if (WayCode != LsPaySdk.WayCode.BANK_FAST) throw new LsPayValidationException("way_code 固定为 BANK_FAST"); Guard.Ipv4(ClientIp); if (string.IsNullOrEmpty(PayOrderId) && string.IsNullOrEmpty(MchOrderNo)) throw new LsPayValidationException("pay_order_id 与 mch_order_no 至少填写一个"); if (!Regex.IsMatch(VerifyCode ?? "", @"^\d{6}$")) throw new LsPayValidationException("verify_code 必须是 6 位数字"); }
}

public sealed class QuickPaySmsCheckRequest : LsPayRequest<QuickPaySmsCheckResponse>
{
    public override Endpoint Endpoint => Endpoint.QuickPaySmsCheck;
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.BANK_FAST;
    public string? ClientIp { get; set; }
    public string? PayOrderId { get; set; }
    public string? MchOrderNo { get; set; }
    public string? VerifyCode { get; set; }
    public override void Validate() { if (WayCode != LsPaySdk.WayCode.BANK_FAST) throw new LsPayValidationException("way_code 固定为 BANK_FAST"); Guard.Ipv4(ClientIp); if (string.IsNullOrEmpty(PayOrderId) && string.IsNullOrEmpty(MchOrderNo)) throw new LsPayValidationException("pay_order_id 与 mch_order_no 至少填写一个"); if (!Regex.IsMatch(VerifyCode ?? "", @"^\d{6}$")) throw new LsPayValidationException("verify_code 必须是 6 位数字"); }
}

public sealed class QuickPayUnbindRequest : LsPayRequest<QuickPayUnbindResponse>
{
    public override Endpoint Endpoint => Endpoint.QuickPayUnbind;
    public string? OrderId { get; set; }
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.BANK_FAST;
    public string? ClientIp { get; set; }
    public QuickPayUnbindChannelExtra? ChannelExtra { get; set; }
    public override void Validate() { Guard.Required(OrderId, "order_id", 30); Guard.Ipv4(ClientIp); if (ChannelExtra == null) throw new LsPayValidationException("channel_extra 必填"); ChannelExtra.Validate(); }
}

public sealed class ElectronicAccountUserOpenRequest : LsPayRequest<ElectronicAccountUserOpenResponse>
{
    public override Endpoint Endpoint => Endpoint.ElectronicAccountUserOpen;
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.APPLY;
    public string? OrderId { get; set; }
    public string? AccountName { get; set; }
    public ElectronicAccountType? AccountType { get; set; }
    public ElectronicAccountRelationType? RelationType { get; set; }
    public string? RelationTypeName { get; set; }
    public string? AccountAlias { get; set; }
    public string? DivisionProfit { get; set; }
    public string? ClientIp { get; set; }
    public ElectronicAccountOpenChannelExtra? ChannelExtra { get; set; }
    public override void Validate() { if (WayCode != LsPaySdk.WayCode.APPLY) throw new LsPayValidationException("way_code 固定为 APPLY"); Guard.Required(OrderId, "order_id", 30); Guard.Required(AccountName, "account_name", 30); if (!AccountType.HasValue) throw new LsPayValidationException("account_type 必填"); if (!RelationType.HasValue) throw new LsPayValidationException("relation_type 必填"); if (RelationType == ElectronicAccountRelationType.CUSTOM) Guard.Required(RelationTypeName, "relation_type_name", 30); Guard.Required(AccountAlias, "account_alias", 30); if (!decimal.TryParse(DivisionProfit, NumberStyles.Number, CultureInfo.InvariantCulture, out var ratio) || ratio < 0 || ratio > 1) throw new LsPayValidationException("division_profit 必须是 0 到 1 之间的十进制比例"); Guard.Ipv4(ClientIp); if (ChannelExtra == null) throw new LsPayValidationException("channel_extra 必填"); ChannelExtra.Validate(AccountType.Value); }
}

public abstract class ElectronicAccountBusinessRequest<TResponse> : LsPayRequest<TResponse> where TResponse : LsPayResponse, new()
{
    public string? OrderId { get; set; }
    public string? AccountNo { get; set; }
    public string? ClientIp { get; set; }
    protected void ValidateBase() { Guard.Required(OrderId, "order_id", 30); Guard.Required(AccountNo, "account_no", 32); Guard.Ipv4(ClientIp); }
}

public sealed class ElectronicAccountBusinessOpenRequest : ElectronicAccountBusinessRequest<ElectronicAccountBusinessOpenResponse>
{
    public override Endpoint Endpoint => Endpoint.ElectronicAccountBusinessOpen;
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.APPLY;
    public ElectronicAccountBusinessChannelExtra? ChannelExtra { get; set; }
    public override void Validate() { if (WayCode != LsPaySdk.WayCode.APPLY) throw new LsPayValidationException("way_code 固定为 APPLY"); ValidateBase(); if (ChannelExtra == null) throw new LsPayValidationException("channel_extra 必填"); ChannelExtra.Validate(); }
}

public sealed class ElectronicAccountBusinessChangeRequest : ElectronicAccountBusinessRequest<ElectronicAccountBusinessChangeResponse>
{
    public override Endpoint Endpoint => Endpoint.ElectronicAccountBusinessChange;
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.APPLY;
    public ElectronicAccountBusinessChangeChannelExtra? ChannelExtra { get; set; }
    public override void Validate() { if (WayCode != LsPaySdk.WayCode.APPLY) throw new LsPayValidationException("way_code 固定为 APPLY"); ValidateBase(); if (ChannelExtra == null) throw new LsPayValidationException("channel_extra 必填"); ChannelExtra.Validate(); }
}

public sealed class ElectronicAccountUserQueryRequest : LsPayRequest<ElectronicAccountUserQueryResponse>
{
    public override Endpoint Endpoint => Endpoint.ElectronicAccountUserQuery;
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.APPLY;
    public string? AccountNo { get; set; }
    public string? ClientIp { get; set; }
    public override void Validate() { if (WayCode != LsPaySdk.WayCode.APPLY) throw new LsPayValidationException("way_code 固定为 APPLY"); Guard.Required(AccountNo, "account_no", 32); Guard.Ipv4(ClientIp); }
}

public sealed class ElectronicAccountSettlementQueryRequest : LsPayRequest<ElectronicAccountSettlementQueryResponse>
{
    public override Endpoint Endpoint => Endpoint.ElectronicAccountSettlementQuery;
    public string? OrderId { get; set; }
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.SETTLE_QUERY;
    public string? AccountNo { get; set; }
    public string? BeginDate { get; set; }
    public string? EndDate { get; set; }
    public int? PageSize { get; set; }
    public int? PageNum { get; set; }
    public override void Validate() { Guard.Required(OrderId, "order_id", 30); Guard.Required(AccountNo, "account_no", 32); var begin = Guard.Date(BeginDate, "begin_date"); var end = Guard.Date(EndDate, "end_date"); if (begin > end || (end - begin).TotalDays > 30) throw new LsPayValidationException("日期范围非法或跨度超过 30 天"); if (PageSize <= 0 || PageNum <= 0) throw new LsPayValidationException("page_size/page_num 必须是正整数"); }
}

public sealed class ElectronicAccountBalanceWithdrawRequest : LsPayRequest<ElectronicAccountBalanceWithdrawResponse>
{
    public override Endpoint Endpoint => Endpoint.ElectronicAccountBalanceWithdraw;
    public string? OrderId { get; set; }
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.BALANCE_WITHDRAW;
    public string? AccountNo { get; set; }
    public string? WithdrawAmount { get; set; }
    public override void Validate() { Guard.Required(OrderId, "order_id", 30); Guard.Required(AccountNo, "account_no", 32); if (!Regex.IsMatch(WithdrawAmount ?? "", @"^(?:0|[1-9]\d*)(?:\.\d{1,2})?$") || !Regex.IsMatch(WithdrawAmount ?? "", "[1-9]")) throw new LsPayValidationException("withdraw_amount 必须是大于 0 且最多保留 2 位小数的元金额"); }
}

public sealed class ElectronicAccountSingleWithdrawQueryRequest : LsPayRequest<ElectronicAccountSingleWithdrawQueryResponse>
{
    public override Endpoint Endpoint => Endpoint.ElectronicAccountSingleWithdrawQuery;
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.WITHDRAW_SINGLE_QUERY;
    public string? AccountNo { get; set; }
    public string? WithdrawDate { get; set; }
    public string? OrderId { get; set; }
    public override void Validate() { Guard.Required(AccountNo, "account_no", 32); Guard.Date(WithdrawDate, "withdraw_date"); Guard.Required(OrderId, "order_id", 30); }
}

public sealed class ElectronicAccountBatchWithdrawQueryRequest : LsPayRequest<ElectronicAccountBatchWithdrawQueryResponse>
{
    public override Endpoint Endpoint => Endpoint.ElectronicAccountBatchWithdrawQuery;
    public WayCode WayCode { get; set; } = LsPaySdk.WayCode.WITHDRAW_QUERY;
    public string? AccountNo { get; set; }
    public string? BeginDate { get; set; }
    public string? EndDate { get; set; }
    public int? PageSize { get; set; }
    public int? PageNum { get; set; }
    public override void Validate() { Guard.Required(AccountNo, "account_no", 32); if (Guard.Date(BeginDate, "begin_date") > Guard.Date(EndDate, "end_date")) throw new LsPayValidationException("begin_date 不能晚于 end_date"); if (PageSize <= 0 || PageNum <= 0) throw new LsPayValidationException("page_size/page_num 必须是正整数"); }
}
