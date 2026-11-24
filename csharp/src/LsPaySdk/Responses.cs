using System;
using System.Collections.Generic;
using System.Globalization;
using System.Text.Json;

namespace LsPaySdk;

public class LsPayResponse
{
    public JsonElement Raw { get; private set; }
    public string? Status { get; private set; }
    public string Message { get; private set; } = "";
    public string? Sign { get; private set; }
    public JsonElement Data { get; private set; }
    public virtual bool IsError => Status == null || (Status != "200" && Status != "0" && !string.Equals(Status, "SUCCESS", StringComparison.OrdinalIgnoreCase));

    internal void Initialize(JsonElement root)
    {
        Raw = root.Clone();
        Status = ReadAny(root, "status", "code", "retCode");
        Message = ReadAny(root, "msg", "retMsg") ?? "";
        Sign = ReadAny(root, "sign");
        Data = ReadData(root);
    }

    public void ThrowIfError()
    {
        if (IsError) throw new LsPayBizException(Status ?? "UNKNOWN", Message);
    }

    public static TResponse Parse<TResponse>(string json) where TResponse : LsPayResponse, new()
    {
        try
        {
            using (var document = JsonDocument.Parse(json))
            {
                if (document.RootElement.ValueKind != JsonValueKind.Object)
                    throw new LsPayProtocolException("立收通响应不是合法 JSON 对象");
                var response = new TResponse();
                response.Initialize(document.RootElement);
                return response;
            }
        }
        catch (JsonException ex) { throw new LsPayProtocolException("立收通响应不是合法 JSON 对象", ex); }
    }

    protected string? String(string key) => ReadAny(Data, key);
    protected long? Integer(string key) => long.TryParse(String(key), NumberStyles.Integer, CultureInfo.InvariantCulture, out var value) ? value : (long?)null;
    protected decimal? Decimal(string key) => decimal.TryParse(String(key), NumberStyles.Number, CultureInfo.InvariantCulture, out var value) ? value : (decimal?)null;
    protected JsonElement? Element(string key) => Data.ValueKind == JsonValueKind.Object && Data.TryGetProperty(key, out var value) ? value : (JsonElement?)null;
    protected string? RootString(string key) => ReadAny(Raw, key);
    protected bool HasStatusEnvelope =>
        Raw.ValueKind == JsonValueKind.Object &&
        (Raw.TryGetProperty("status", out _) || Raw.TryGetProperty("code", out _) || Raw.TryGetProperty("retCode", out _));

    private static JsonElement ReadData(JsonElement root)
    {
        if (!root.TryGetProperty("data", out var data) || data.ValueKind == JsonValueKind.Null)
            return JsonDocument.Parse("{}").RootElement.Clone();
        if (data.ValueKind == JsonValueKind.String)
        {
            var text = data.GetString();
            if (!string.IsNullOrWhiteSpace(text))
            {
                try { return JsonDocument.Parse(text!).RootElement.Clone(); } catch (JsonException) { }
            }
        }
        return data.Clone();
    }

    internal static string? ReadAny(JsonElement element, params string[] keys)
    {
        if (element.ValueKind != JsonValueKind.Object) return null;
        foreach (var key in keys)
        {
            if (!element.TryGetProperty(key, out var value) || value.ValueKind == JsonValueKind.Null) continue;
            return value.ValueKind == JsonValueKind.String ? value.GetString() : value.GetRawText();
        }
        return null;
    }
}

public class UnifiedOrderResponse : LsPayResponse
{
    public string? PayOrderId => String("pay_order_id");
    public string? MchOrderNo => String("mch_order_no");
    public long? Amount => Integer("amount");
    public OrderState? OrderState => Integer("order_state").HasValue ? (LsPaySdk.OrderState?)Integer("order_state")!.Value : null;
    public string? PayInfo => String("pay_info");
    public string? TransStatus => String("trans_status");
    public string? ErrorCode => String("err_code");
    public string? ErrorMessage => String("err_msg");
    public bool IsAccepted => !IsError && string.Equals(TransStatus, "S", StringComparison.OrdinalIgnoreCase);
    public bool IsPaid => OrderState == LsPaySdk.OrderState.Paid;
}

public sealed class QueryOrderResponse : UnifiedOrderResponse { }
public sealed class BalancePayResponse : UnifiedOrderResponse { }
public sealed class QuickPayApplyResponse : UnifiedOrderResponse { }

public class RefundResponse : LsPayResponse
{
    public string? RefundOrderId => String("refund_order_id") ?? String("order_refund_id") ?? String("order_refund_Id");
    public string? MchRefundNo => String("mch_refund_no");
    public string? ChannelOrderNo => String("channel_order_no") ?? String("channel_rrder_no");
    public string? ErrorCode => String("err_code");
    public string? ErrorMessage => String("err_msg");
    public RefundState? State => Integer("state").HasValue ? (RefundState?)Integer("state")!.Value : null;
    public long? PayAmount => Integer("pay_amount");
    public long? RefundAmount => Integer("refund_amount");
    public bool IsAccepted => !IsError;
    public bool IsRefunded => !IsError && State == RefundState.Succeeded;
}

public sealed class QueryRefundResponse : RefundResponse
{
    public string? PayOrderId => String("pay_order_id");
    public string? MchNo => String("mch_no");
    public string? AppId => String("app_id");
    public string? Currency => String("currency");
    public string? ExtParam => String("ext_param");
    /// <summary>10 位秒级时间戳。</summary>
    public long? AddTime => Integer("add_time");
    /// <summary>10 位秒级时间戳。</summary>
    public long? SuccessTime => Integer("success_time");
}

public class DelayTransConfirmResponse : LsPayResponse
{
    public long? UnconfirmAmount => Integer("unconfirm_amount");
    public long? ConfirmedAmount => Integer("confirmed_amount");
    public string? ConfirmOrderId => String("confirm_order_id");
    public string? BatchOrderId => String("batch_order_id");
    public string? TransStatus => String("trans_status");
    public string? ErrorCode => String("err_code");
    public string? ErrorMessage => String("err_msg");
    public bool IsSucceeded => !IsError && TransStatus == "S";
}

public sealed class DelayTransConfirmRefundResponse : LsPayResponse
{
    public long? UnconfirmAmount => Integer("unconfirm_amount");
    public long? ConfirmedAmount => Integer("confirmed_amount");
    public string? ConfirmOrderId => String("confirm_order_id");
    public string? ConfirmRefundNo => String("confirm_refund_no");
    public string? TransStatus => String("trans_status");
    public string? ErrorCode => String("err_code");
    public string? ErrorMessage => String("err_msg");
    public bool IsSucceeded => !IsError && TransStatus == "S";
}

public sealed class DelayTransUnconfirmQueryResponse : LsPayResponse { public long? UnconfirmAmount => Integer("unconfirm_amount"); }

public sealed class DelayTransCreateTaskResponse : LsPayResponse
{
    public string? TaskId => String("task_id");
    public string? OrderNo => String("order_no");
    public string? AccountNo => String("account_no");
    public string? AccountName => String("account_name");
    public long? Amount => Integer("amount");
    public string? TaskStatus => String("status");
    public int? SelectedOrders => Integer("selected_orders").HasValue ? (int?)Integer("selected_orders")!.Value : null;
    public string? FirstOrderTime => String("first_order_time");
    public string? LastOrderTime => String("last_order_time");
    public bool IsCreated => !IsError && TaskStatus == "PENDING";
}

public abstract class QuickPayResponse : LsPayResponse
{
    public string? TransStatus => QuickPayValue("trans_status");
    public override bool IsError => HasStatusEnvelope ? base.IsError : TransStatus == null || TransStatus == "F";
    public bool IsAccepted => !IsError;
    public bool IsSucceeded => !IsError && TransStatus == "S";
    public bool IsProcessing => !IsError && TransStatus == "P";
    protected string? QuickPayValue(string key) => String(key) ?? RootString(key);
}

public sealed class QuickPayBindApplyResponse : QuickPayResponse { public string? TransId => QuickPayValue("trans_id"); public string? OrderId => QuickPayValue("order_id"); public string? Description => QuickPayValue("resp_desc"); }
public sealed class QuickPayBindConfirmResponse : QuickPayResponse { public string? ChannelUser => QuickPayValue("channel_user"); public string? ChannelCardId => QuickPayValue("channel_card_id"); public string? Description => QuickPayValue("resp_desc"); }
public sealed class QuickPayConfirmResponse : QuickPayResponse { public string? ErrorMessage => QuickPayValue("err_msg"); }
public sealed class QuickPaySmsCheckResponse : QuickPayResponse { public string? ErrorMessage => QuickPayValue("err_msg"); }
public sealed class QuickPayUnbindResponse : QuickPayResponse { public string? OrderId => QuickPayValue("order_id"); public string? Description => QuickPayValue("resp_desc"); }

public abstract class ElectronicAccountResponse : LsPayResponse
{
    public string? TransStatus => AccountValue("trans_status");
    public override bool IsError => HasStatusEnvelope ? base.IsError : TransStatus == null || TransStatus == "F";
    public bool IsSucceeded => !IsError && TransStatus == "S";
    public bool IsProcessing => !IsError && TransStatus == "P";
    protected string? AccountValue(string key) => String(key) ?? RootString(key);
}

public sealed class ElectronicAccountUserOpenResponse : ElectronicAccountResponse { public string? AccountNo => AccountValue("account_no"); public string? Description => AccountValue("resp_desc"); }
public sealed class ElectronicAccountBusinessOpenResponse : ElectronicAccountResponse { public string? AccountNo => AccountValue("account_no"); public string? Description => AccountValue("resp_desc"); }
public sealed class ElectronicAccountBusinessChangeResponse : ElectronicAccountResponse { public string? AccountNo => AccountValue("account_no"); public string? Description => AccountValue("resp_desc"); }
public sealed class ElectronicAccountUserQueryResponse : ElectronicAccountResponse
{
    public string? AccountNo => AccountValue("account_no");
    public string? AccountName => AccountValue("account_name");
    public string? AccountType => AccountValue("account_type");
    public string? RelationType => AccountValue("relation_type");
    public string? RelationTypeName => AccountValue("relation_type_name");
    public string? DivisionProfit => AccountValue("division_profit");
    public string? ChannelStatus => AccountValue("channel_status");
    public string? Description => AccountValue("resp_desc");
    public string? ChannelExtra => AccountValue("channel_extra");
    /// <summary>查询资料中的 channel_status 是否为 1。开户成功看开户接口的 trans_status。</summary>
    public bool IsOnboarded => IsSucceeded && ChannelStatus == "1";
}

public sealed class ElectronicAccountSettlementQueryResponse : LsPayResponse
{
    public IReadOnlyList<JsonElement> Records
    {
        get
        {
            if (Data.ValueKind == JsonValueKind.Array) return ToList(Data);
            if (Data.ValueKind != JsonValueKind.Object) return Array.Empty<JsonElement>();
            foreach (var key in new[] { "records", "list", "rows" })
                if (Data.TryGetProperty(key, out var collection) && collection.ValueKind == JsonValueKind.Array)
                    return ToList(collection);
            return Data.TryGetProperty("trans_id", out _) ? new[] { Data.Clone() } : Array.Empty<JsonElement>();
        }
    }
    public JsonElement? FirstRecord => Records.Count > 0 ? Records[0] : (JsonElement?)null;
    public string? TransId => First("trans_id");
    public string? TransDate => First("trans_date");
    public string? SettleCycle => First("settle_cycle");
    public string? CardNo => First("card_no");
    public string? CardName => First("card_name");
    public string? BankCode => First("bank_code");
    public string? TransAmount => First("trans_amt");
    public string? FeeAmount => First("fee_amt");
    public string? TransStatus => First("trans_stat");
    public string? SettleType => First("settle_type");
    public string? SettleAbstract => First("settle_abstract");
    public string? SettleBatchNo => First("settle_batch_no");
    public string? SettleDescription => First("settle_desc");
    public string? FirstTransId => TransId;
    private string? First(string key) => FirstRecord.HasValue ? ReadAny(FirstRecord.Value, key) : null;
    private static IReadOnlyList<JsonElement> ToList(JsonElement array)
    {
        var result = new List<JsonElement>();
        foreach (var item in array.EnumerateArray()) if (item.ValueKind == JsonValueKind.Object) result.Add(item.Clone());
        return result;
    }
}

public sealed class ElectronicAccountBalanceWithdrawResponse : LsPayResponse
{
    public string? AcceptanceStatus => String("status");
    public string? AcceptanceMessage => String("err_msg") ?? String("msg") ?? String("resp_desc");
    public bool IsAccepted => !IsError &&
        (string.Equals(AcceptanceStatus, "success", StringComparison.OrdinalIgnoreCase) ||
         string.Equals(AcceptanceStatus, "succeess", StringComparison.OrdinalIgnoreCase));
}

public sealed class ElectronicAccountSingleWithdrawQueryResponse : LsPayResponse
{
    public string? WithdrawDate => String("withdraw_date");
    public string? OrderId => String("order_id");
    public decimal? CashAmount => Decimal("cash_amt");
    public decimal? FeeAmount => Decimal("fee_amt");
    public string? TransStatus => String("trans_status");
    public string? Description => String("trans_desc");
    public string? ChannelStatus => String("status");
    public string? ChannelMessage => String("err_msg");
    public bool IsPending => !IsError && TransStatus == "pending";
    public bool IsFailed => !IsError && TransStatus == "failed";
    public bool IsSucceeded => !IsError && TransStatus == "succeeded";
    public bool IsChannelSuccessful =>
        string.Equals(ChannelStatus, "success", StringComparison.OrdinalIgnoreCase) ||
        string.Equals(ChannelStatus, "succeess", StringComparison.OrdinalIgnoreCase);
}

public sealed class ElectronicAccountBatchWithdrawQueryResponse : LsPayResponse
{
    public int? Count => Integer("count").HasValue ? (int?)Integer("count")!.Value : null;
    public int? PageSize => Integer("page_size").HasValue ? (int?)Integer("page_size")!.Value : null;
    public int? PageNum => Integer("page_num").HasValue ? (int?)Integer("page_num")!.Value : null;
    public string? ChannelStatus => String("status");
    public IReadOnlyList<JsonElement> Records
    {
        get
        {
            if (Data.ValueKind != JsonValueKind.Object ||
                !Data.TryGetProperty("data", out var records) ||
                records.ValueKind != JsonValueKind.Array) return Array.Empty<JsonElement>();
            var result = new List<JsonElement>();
            foreach (var item in records.EnumerateArray()) if (item.ValueKind == JsonValueKind.Object) result.Add(item.Clone());
            return result;
        }
    }
    public JsonElement? FirstRecord => Records.Count > 0 ? Records[0] : (JsonElement?)null;
    public string? SettleDate => First("settle_date");
    public string? CardNo => First("card_no");
    public string? CardName => First("card_name");
    public string? BankName => First("bank_name");
    public string? BatchTransType => First("batch_trans_type");
    public string? CashAmount => First("cash_amt");
    public string? FeeAmount => First("fee_amt");
    public string? FeeFlag => First("fee_flag");
    public string? OriginalRequestDate => First("org_req_date");
    public string? OrderId => First("order_id");
    public string? TransStatus => First("trans_status");
    public string? Remark => First("remark");
    public string? TransDescription => First("trans_desc");
    public bool IsFirstRecordPending => !IsError && TransStatus == "pending";
    public bool IsFirstRecordFailed => !IsError && TransStatus == "failed";
    public bool IsFirstRecordSucceeded => !IsError && TransStatus == "succeeded";
    public bool IsChannelSuccessful =>
        string.Equals(ChannelStatus, "success", StringComparison.OrdinalIgnoreCase) ||
        string.Equals(ChannelStatus, "succeess", StringComparison.OrdinalIgnoreCase);
    private string? First(string key) => FirstRecord.HasValue ? ReadAny(FirstRecord.Value, key) : null;
}

public sealed class BalanceQueryResponse : LsPayResponse
{
    public override bool IsError => !HasStatusEnvelope && RootString("acct_type") != null ? false : base.IsError;
    public string? AccountType => BalanceValue("acct_type");
    public string? AccountStatus => BalanceValue("acct_stat");
    public decimal? BalanceAmount => BalanceDecimal("balance_amt");
    public decimal? AvailableBalance => BalanceDecimal("avl_balance");
    public decimal? FrozenBalance => BalanceDecimal("frz_bal");
    public decimal? HistoricalBalance => BalanceDecimal("dm_amt");
    public bool IsNormal => !IsError && AccountStatus == "N";
    private string? BalanceValue(string key) => String(key) ?? RootString(key);
    private decimal? BalanceDecimal(string key) =>
        decimal.TryParse(BalanceValue(key), NumberStyles.Number, CultureInfo.InvariantCulture, out var value) ? value : (decimal?)null;
}
