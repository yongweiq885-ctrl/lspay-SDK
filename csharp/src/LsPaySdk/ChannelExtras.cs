using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Text.RegularExpressions;

namespace LsPaySdk;

public class ChannelExtra
{
    protected readonly Dictionary<string, object?> Values = new Dictionary<string, object?>(StringComparer.Ordinal);

    public ChannelExtra Set(string key, object? value)
    {
        if (string.IsNullOrWhiteSpace(key)) throw new LsPayValidationException("channel_extra 参数名不能为空");
        if (value == null || (value is string text && text == "")) Values.Remove(key);
        else Values[key] = value;
        return this;
    }

    public virtual void Validate() { }
    public Dictionary<string, object?> ToDictionary() { Validate(); return new Dictionary<string, object?>(Values, StringComparer.Ordinal); }
    public string ToJson() => JsonSerializer.Serialize(ToDictionary(), Json.Options);
}

public sealed class DivisionReceiver
{
    public string TransIn { get; set; }
    public long? DivAmount { get; set; }
    public string? Description { get; set; }

    public DivisionReceiver(string transIn, long? divAmount = null, string? description = null)
    {
        Guard.Required(transIn, "trans_in");
        if (divAmount.HasValue && divAmount <= 0) throw new LsPayValidationException("div_amt 必须是大于 0 的整数");
        TransIn = transIn;
        DivAmount = divAmount;
        Description = description;
    }

    internal Dictionary<string, object?> ToDictionary()
    {
        var result = new Dictionary<string, object?> { ["trans_in"] = TransIn };
        if (DivAmount.HasValue) result["div_amt"] = DivAmount.Value;
        if (Description != null) result["description"] = Description;
        return result;
    }
}

public class DivisionChannelExtra : ChannelExtra
{
    private readonly List<DivisionReceiver> _receivers = new List<DivisionReceiver>();
    public DivisionChannelExtra AddReceiver(DivisionReceiver receiver) { _receivers.Add(receiver ?? throw new ArgumentNullException(nameof(receiver))); return this; }
    public DivisionChannelExtra SetReceivers(IEnumerable<DivisionReceiver> receivers) { _receivers.Clear(); _receivers.AddRange(receivers); return this; }
    public override void Validate() { if (_receivers.Count > 0) Values["div_list"] = _receivers.Select(x => x.ToDictionary()).ToList(); }
}

public sealed class UnifiedOrderChannelExtra : DivisionChannelExtra
{
    public UnifiedOrderChannelExtra BuyerId(string value) { Set("buyer_id", value); return this; }
    public UnifiedOrderChannelExtra OpenId(string value) { Set("openid", value); return this; }
    public UnifiedOrderChannelExtra AuthCode(string value) { Set("auth_code", value); return this; }
    public UnifiedOrderChannelExtra ChannelUser(string value) { Set("channel_user", value); return this; }
    public UnifiedOrderChannelExtra ChannelCardId(string value) { Set("channel_card_id", value); return this; }
    public UnifiedOrderChannelExtra PayData(PayDataType value) { Set("pay_data_type", ProtocolValue.Of(value)); return this; }
}

public sealed class BalancePayChannelExtra : DivisionChannelExtra
{
    public BalancePayChannelExtra TransOut(string value) { Set("trans_out", value); return this; }
}

public sealed class QuickPayBindChannelExtra : ChannelExtra
{
    public QuickPayBindChannelExtra(string mchUserId, string cardId, string cardName, string certId, string cardMobile)
    {
        Set("mch_user_id", mchUserId); Set("card_id", cardId); Set("card_name", cardName); Set("cert_id", certId); Set("card_mp", cardMobile); Set("cert_type", "00");
    }
    public QuickPayBindChannelExtra CertType(string value) { Set("cert_type", value); return this; }
    public QuickPayBindChannelExtra CertValidity(string type, string begin, string? end = null) { Set("cert_validity_type", type); Set("cert_begin_date", begin); Set("cert_end_date", end); return this; }
    public QuickPayBindChannelExtra VipCode(string value) { Set("vip_code", value); return this; }
    public QuickPayBindChannelExtra Expiration(string value) { Set("expiration", value); return this; }
    public QuickPayBindChannelExtra DebitCreditType(string value) { Set("dc_type", value); return this; }
    public override void Validate() { Required("mch_user_id", "card_id", "card_name", "cert_id", "card_mp", "cert_type"); }
    private void Required(params string[] fields) { foreach (var field in fields) if (!Values.TryGetValue(field, out var value) || string.IsNullOrWhiteSpace(Convert.ToString(value))) throw new LsPayValidationException("channel_extra." + field + " 必填"); }
}

public sealed class QuickPayBindConfirmChannelExtra : ChannelExtra
{
    public QuickPayBindConfirmChannelExtra(string mchUserId, string cardId, string cardName, string certId, string cardMobile)
    {
        Set("mch_user_id", mchUserId); Set("card_id", cardId); Set("card_name", cardName); Set("cert_id", certId); Set("card_mp", cardMobile); Set("cert_type", "00");
    }
    public QuickPayBindConfirmChannelExtra DebitCreditType(string value) { Set("dc_type", value); return this; }
    public override void Validate() { foreach (var field in new[] { "mch_user_id", "card_id", "card_name", "cert_id", "card_mp" }) if (!Values.ContainsKey(field)) throw new LsPayValidationException("channel_extra." + field + " 必填"); }
}

public sealed class QuickPayUnbindChannelExtra : ChannelExtra
{
    public QuickPayUnbindChannelExtra(string mchUserId, string channelCardId) { Set("mch_user_id", mchUserId); Set("channel_card_id", channelCardId); }
    public override void Validate() { if (!Values.ContainsKey("mch_user_id") || !Values.ContainsKey("channel_card_id")) throw new LsPayValidationException("mch_user_id 与 channel_card_id 必填"); }
}

public sealed class ElectronicAccountUserInfo
{
    internal readonly Dictionary<string, object?> Values = new Dictionary<string, object?>();
    public ElectronicAccountUserInfo(string name, string certNo, string mobileNo)
    {
        Values["cert_type"] = "00"; Values["name"] = name; Values["cert_no"] = certNo; Values["mobile_no"] = mobileNo;
    }
    public ElectronicAccountUserInfo CertificateValidity(string type, string begin, string? end = null) { Values["cert_validity_type"] = type; Values["cert_begin_date"] = begin; if (end != null) Values["cert_end_date"] = end; return this; }
    public ElectronicAccountUserInfo Email(string value) { Values["email"] = value; return this; }
    internal void Validate() { foreach (var field in new[] { "name", "cert_no", "mobile_no" }) if (string.IsNullOrWhiteSpace(Convert.ToString(Values[field]))) throw new LsPayValidationException("user_info." + field + " 必填"); }
}

public sealed class ElectronicAccountMerchantInfo
{
    internal readonly Dictionary<string, object?> Values = new Dictionary<string, object?>();
    public ElectronicAccountMerchantInfo Set(string key, string value) { Values[key] = value; return this; }
    internal void Validate()
    {
        foreach (var field in new[] { "reg_name", "short_name", "license_code", "reg_prov_id", "reg_area_id", "reg_district_id", "reg_detail", "legal_name", "legal_cert_no", "contact_name", "contact_mobile" })
            if (!Values.TryGetValue(field, out var value) || string.IsNullOrWhiteSpace(Convert.ToString(value))) throw new LsPayValidationException("mer_info." + field + " 必填");
    }
}

public sealed class ElectronicAccountOpenChannelExtra : ChannelExtra
{
    private readonly ElectronicAccountUserInfo? _user;
    private readonly ElectronicAccountMerchantInfo? _merchant;
    public ElectronicAccountOpenChannelExtra(ElectronicAccountUserInfo user) { _user = user; }
    public ElectronicAccountOpenChannelExtra(ElectronicAccountMerchantInfo merchant) { _merchant = merchant; }
    public void Validate(ElectronicAccountType type)
    {
        if (type == ElectronicAccountType.Personal)
        {
            if (_user == null) throw new LsPayValidationException("account_type=1 时 channel_extra.user_info 必填");
            _user.Validate(); Values["user_info"] = _user.Values;
        }
        else
        {
            if (_merchant == null) throw new LsPayValidationException("account_type=2 或 3 时 channel_extra.mer_info 必填");
            _merchant.Validate(); Values["mer_info"] = _merchant.Values;
        }
    }
}

public sealed class ElectronicAccountSettlementCard
{
    internal readonly Dictionary<string, object?> Values = new Dictionary<string, object?>();
    public ElectronicAccountSettlementCard(string cardType, string cardName, string cardNo)
    { Values["card_type"] = cardType; Values["card_name"] = cardName; Values["card_no"] = cardNo; }
    public ElectronicAccountSettlementCard Set(string key, string value) { Values[key] = value; return this; }
    internal void Validate() { foreach (var field in new[] { "card_type", "card_name", "card_no" }) if (!Values.ContainsKey(field)) throw new LsPayValidationException("card_info." + field + " 必填"); }
}

public sealed class ElectronicAccountModifiedCard
{
    internal readonly Dictionary<string, object?> Values = new Dictionary<string, object?>();
    public ElectronicAccountModifiedCard(string cardType, string cardName, string cardNo) { Values["card_type"] = cardType; Values["card_name"] = cardName; Values["card_no"] = cardNo; }
    internal void Validate() { foreach (var field in new[] { "card_type", "card_name", "card_no" }) if (!Values.ContainsKey(field)) throw new LsPayValidationException("card_info." + field + " 必填"); }
}

public sealed class ElectronicAccountSettleConfig
{
    internal readonly Dictionary<string, object?> Values = new Dictionary<string, object?>();
    public ElectronicAccountSettleConfig(string settleCycle, string settleStatus) { Values["settle_cycle"] = settleCycle; Values["settle_status"] = settleStatus; }
    internal void Validate() { if (!Values.ContainsKey("settle_cycle") || !Values.ContainsKey("settle_status")) throw new LsPayValidationException("settle_config 字段不完整"); }
}

public sealed class ElectronicAccountCashConfig
{
    internal readonly Dictionary<string, object?> Values = new Dictionary<string, object?>();
    public ElectronicAccountCashConfig(string cashType, string switchState, string outFeeFlag) { Values["cash_type"] = cashType; Values["switch_state"] = switchState; Values["out_fee_flag"] = outFeeFlag; }
    internal void Validate() { foreach (var field in new[] { "cash_type", "switch_state", "out_fee_flag" }) if (!Values.ContainsKey(field)) throw new LsPayValidationException("cash_config." + field + " 必填"); }
}

public sealed class ElectronicAccountBusinessChannelExtra : ChannelExtra
{
    private readonly ElectronicAccountSettlementCard _card;
    private readonly ElectronicAccountSettleConfig _settle;
    private readonly List<ElectronicAccountCashConfig> _cash = new List<ElectronicAccountCashConfig>();
    public ElectronicAccountBusinessChannelExtra(ElectronicAccountSettlementCard card, ElectronicAccountSettleConfig settle) { _card = card; _settle = settle; }
    public ElectronicAccountBusinessChannelExtra AddCash(ElectronicAccountCashConfig cash) { _cash.Add(cash); return this; }
    public override void Validate() { _card.Validate(); _settle.Validate(); if (_cash.Count == 0) throw new LsPayValidationException("cash_config 必须是非空数组"); foreach (var item in _cash) item.Validate(); Values["card_info"] = _card.Values; Values["settle_config"] = _settle.Values; Values["cash_config"] = _cash.Select(x => x.Values).ToList(); }
}

public sealed class ElectronicAccountBusinessChangeChannelExtra : ChannelExtra
{
    private readonly ElectronicAccountModifiedCard _card;
    private readonly ElectronicAccountSettleConfig _settle;
    private readonly List<ElectronicAccountCashConfig> _cash = new List<ElectronicAccountCashConfig>();
    public ElectronicAccountBusinessChangeChannelExtra(ElectronicAccountModifiedCard card, ElectronicAccountSettleConfig settle) { _card = card; _settle = settle; }
    public ElectronicAccountBusinessChangeChannelExtra AddCash(ElectronicAccountCashConfig cash) { _cash.Add(cash); return this; }
    public override void Validate() { _card.Validate(); _settle.Validate(); if (_cash.Count == 0) throw new LsPayValidationException("cash_config 必须是非空数组"); foreach (var item in _cash) item.Validate(); Values["card_info"] = _card.Values; Values["settle_config"] = _settle.Values; Values["cash_config"] = _cash.Select(x => x.Values).ToList(); }
}
