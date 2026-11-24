package tech.lspay.sdk.request;

public final class UnifiedOrderChannelExtra extends DivisionChannelExtra {
    public UnifiedOrderChannelExtra setBuyerId(String v) { set("buyer_id", v); return this; }
    public UnifiedOrderChannelExtra setOpenid(String v) { set("openid", v); return this; }
    public UnifiedOrderChannelExtra setAuthCode(String v) { set("auth_code", v); return this; }
    public UnifiedOrderChannelExtra setChannelUser(String v) { set("channel_user", v); return this; }
    public UnifiedOrderChannelExtra setChannelCardId(String v) { set("channel_card_id", v); return this; }
    public UnifiedOrderChannelExtra setPayDataType(String v) { set("pay_data_type", v); return this; }
}
