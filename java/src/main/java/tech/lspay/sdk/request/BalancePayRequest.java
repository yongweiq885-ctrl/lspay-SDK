package tech.lspay.sdk.request;

import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.response.BalancePayResponse;

import java.util.Map;

public class BalancePayRequest extends UnifiedOrderRequest {
    public BalancePayRequest() { wayCode = WayCodeEnum.BALANCE_PAY; divisionMode = "1"; }
    @Override public Class<BalancePayResponse> getResponseClass() { return BalancePayResponse.class; }
    @Override public BalancePayRequest setWayCode(String v) { fixed("way_code", v, WayCodeEnum.BALANCE_PAY); return this; }
    @Override protected boolean isAllowedWayCode(String value) { return WayCodeEnum.BALANCE_PAY.equals(value); }
    public BalancePayRequest setChannelExtra(BalancePayChannelExtra v) { channelExtra = v == null ? null : v.toJson(); return this; }
    @Override public void validate() {
        super.validate(); fixed("way_code", wayCode, WayCodeEnum.BALANCE_PAY);
        fixed("division_mode", divisionMode, "1");
        Map<String,Object> extra = parseObject(channelExtra, "channel_extra");
        required("channel_extra.trans_out", extra.get("trans_out") == null ? null : String.valueOf(extra.get("trans_out")), 128);
        if (!(extra.get("div_list") instanceof java.util.Collection))
            throw new IllegalArgumentException("channel_extra.div_list 必填");
    }
}
