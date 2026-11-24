using System;
using System.Collections.Generic;
using System.Collections.ObjectModel;
using System.Reflection;

namespace LsPaySdk;

[AttributeUsage(AttributeTargets.Field)]
internal sealed class WireValueAttribute : Attribute
{
    public string Value { get; }
    public WireValueAttribute(string value) { Value = value; }
}

public enum Endpoint
{
    [WireValue("gateway/unifiedorder")] UnifiedOrder,
    [WireValue("gateway/query")] QueryOrder,
    [WireValue("gateway/refund")] Refund,
    [WireValue("gateway/refund/query")] QueryRefund,
    [WireValue("gateway/delaytrans/confirm")] DelayTransConfirm,
    [WireValue("gateway/delaytrans/confirmrefund")] DelayTransConfirmRefund,
    [WireValue("gateway/delaytrans/unconfirm/query")] DelayTransUnconfirmQuery,
    [WireValue("gateway/delaytrans/createtask")] DelayTransCreateTask,
    [WireValue("gateway/quickpay/buckleapply")] QuickPayBindApply,
    [WireValue("gateway/quickpay/buckleconfirm")] QuickPayBindConfirm,
    [WireValue("gateway/quickpay/payconfirm")] QuickPayConfirm,
    [WireValue("gateway/quickpay/payunbind")] QuickPayUnbind,
    [WireValue("gateway/quickpay/smscheck")] QuickPaySmsCheck,
    [WireValue("gateway/user/personapply")] ElectronicAccountUserOpen,
    [WireValue("gateway/user/personconfirm")] ElectronicAccountBusinessOpen,
    [WireValue("gateway/user/personmodify")] ElectronicAccountBusinessChange,
    [WireValue("gateway/user/query")] ElectronicAccountUserQuery,
    [WireValue("gateway/user/settlement/query")] ElectronicAccountSettlementQuery,
    [WireValue("gateway/user/balance/withdraw")] ElectronicAccountBalanceWithdraw,
    [WireValue("gateway/user/balance/withdraw/single/query")] ElectronicAccountSingleWithdrawQuery,
    [WireValue("gateway/user/balance/withdraw/query")] ElectronicAccountBatchWithdrawQuery,
    [WireValue("gateway/user/balance/query")] BalanceQuery
}

public enum WayCode
{
    ALI_BAR, ALI_PUB, ALI_APP, ALI_H5, ALI_LITE_H5, ALI_PC, ALI_QR, ALI_LITE,
    WX_BAR, WX_PUB, WX_LITE, WX_APP, WX_LITE_H5, WX_LITE_JUMP, WX_NATIVE,
    YSF_BAR, YSF_JSAPI, CUP_QR, BANK_FAST, BANK_FAST_JUMP, APPLY, BALANCE_PAY,
    BALANCE_QUERY, SETTLE_QUERY, BALANCE_WITHDRAW, WITHDRAW_SINGLE_QUERY, WITHDRAW_QUERY
}

public static class WayCodeCatalog
{
    private static readonly IReadOnlyCollection<WayCode> PaymentMethodList =
        new ReadOnlyCollection<WayCode>(new List<WayCode>
    {
        WayCode.ALI_BAR, WayCode.ALI_PUB, WayCode.ALI_APP, WayCode.ALI_H5,
        WayCode.ALI_LITE_H5, WayCode.ALI_PC, WayCode.ALI_QR, WayCode.ALI_LITE,
        WayCode.WX_BAR, WayCode.WX_PUB, WayCode.WX_LITE, WayCode.WX_APP,
        WayCode.WX_LITE_H5, WayCode.WX_LITE_JUMP, WayCode.WX_NATIVE,
        WayCode.YSF_BAR, WayCode.YSF_JSAPI, WayCode.CUP_QR,
        WayCode.BANK_FAST, WayCode.BANK_FAST_JUMP
    });
    private static readonly HashSet<WayCode> PaymentMethodSet = new HashSet<WayCode>(PaymentMethodList);

    public static IReadOnlyCollection<WayCode> PaymentMethods => PaymentMethodList;

    public static bool IsPaymentMethod(WayCode wayCode) => PaymentMethodSet.Contains(wayCode);
}

public enum OrderState { Created = 0, Paying = 1, Paid = 2, Failed = 3, Revoked = 4, Refunded = 5, Closed = 6 }
public enum RefundState { Created = 0, Refunding = 1, Succeeded = 2, Failed = 3, Closed = 4 }
public enum ElectronicAccountType { Personal = 1, Enterprise = 2, IndividualBusiness = 3 }
public enum ElectronicAccountTransStatus { [WireValue("F")] Failed, [WireValue("P")] Processing, [WireValue("S")] Succeeded }
public enum QuickPayTransStatus { [WireValue("F")] Failed, [WireValue("P")] Processing, [WireValue("S")] Succeeded }
public enum ElectronicAccountChannelStatus { [WireValue("0")] Pending, [WireValue("1")] Succeeded, [WireValue("2")] Failed }
public enum ElectronicAccountBalanceStatus { [WireValue("N")] Normal, [WireValue("C")] Closed, [WireValue("F")] Frozen, [WireValue("D")] Cancelled }
public enum ElectronicAccountWithdrawStatus { [WireValue("pending")] Pending, [WireValue("failed")] Failed, [WireValue("succeeded")] Succeeded }
public enum DelayTransTaskStatus { PENDING, PROCESSING, COMPLETED, FAILED, PARTIAL }
public enum PayDataType { [WireValue("cashier")] Cashier, [WireValue("pay_url")] PayUrl, [WireValue("wxapp")] WxApp, [WireValue("aliapp")] AliApp, [WireValue("ysfapp")] YsfApp, [WireValue("code_url")] CodeUrl, [WireValue("code_img_url")] CodeImageUrl, [WireValue("none")] None }
public enum ElectronicAccountRelationType { SERVICE_PROVIDER, STORE, STAFF, STORE_OWNER, PARTNER, HEADQUARTER, BRAND, DISTRIBUTOR, USER, SUPPLIER, CUSTOM }

public static class ProtocolValue
{
    public static string Of(Enum value)
    {
        var field = value.GetType().GetField(value.ToString());
        var attribute = field?.GetCustomAttribute<WireValueAttribute>();
        return attribute?.Value ?? value.ToString();
    }

    public static T Parse<T>(string value) where T : struct
    {
        foreach (var item in Enum.GetValues(typeof(T)))
        {
            var candidate = (Enum)item;
            if (string.Equals(Of(candidate), value, StringComparison.OrdinalIgnoreCase)) return (T)item;
        }
        throw new LsPayProtocolException($"无法将 {value} 转换为 {typeof(T).Name}");
    }
}
