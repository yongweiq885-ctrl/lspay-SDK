package tech.lspay.sdk.request;

import com.alibaba.fastjson2.JSON;
import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.response.UnifiedOrderResponse;

import java.util.Collection;
import java.util.Map;

/** 统一下单请求。端点 /gateway/unifiedorder。 */
public class UnifiedOrderRequest extends BaseRequest {

    public String mchOrderNo;
    public String wayCode;
    public Long   amount;
    public String currency = "cny";
    public String subject;
    public String body;
    public String channelExtra;
    public String clientIp;
    public String notifyUrl;
    public String returnUrl;
    public String expiredTime;
    public String divisionMode;
    public String extParam;
    public Integer pollingMode;

    @Override
    public String getFunctionCode() { return EndpointEnum.UNIFIED_ORDER; }
    @Override
    public Class<? extends UnifiedOrderResponse> getResponseClass() { return UnifiedOrderResponse.class; }

    // ============ 链式 setter ============

    public UnifiedOrderRequest setMchOrderNo(String v)   { this.mchOrderNo = v;   return this; }
    public UnifiedOrderRequest setWayCode(String v)      { this.wayCode = v;      return this; }
    public UnifiedOrderRequest setAmount(long v)         { this.amount = v;       return this; }
    public UnifiedOrderRequest setCurrency(String v)     { this.currency = v;     return this; }
    public UnifiedOrderRequest setSubject(String v)      { this.subject = v;      return this; }
    public UnifiedOrderRequest setBody(String v)         { this.body = v;         return this; }
    public UnifiedOrderRequest setClientIp(String v)     { this.clientIp = v;     return this; }
    public UnifiedOrderRequest setNotifyUrl(String v)    { this.notifyUrl = v;    return this; }
    public UnifiedOrderRequest setReturnUrl(String v)    { this.returnUrl = v;    return this; }
    public UnifiedOrderRequest setExpiredTime(String v)  { this.expiredTime = v;  return this; }
    public UnifiedOrderRequest setDivisionMode(String v) { this.divisionMode = v; return this; }
    public UnifiedOrderRequest setExpiredTime(long v)     { this.expiredTime = String.valueOf(v); return this; }
    public UnifiedOrderRequest setDivisionMode(int v)     { this.divisionMode = String.valueOf(v); return this; }
    public UnifiedOrderRequest setPollingMode(int v)      { this.pollingMode = v; return this; }

    /** 渠道扩展参数：字符串原样使用，Map/Collection 自动 JSON 化 */
    public UnifiedOrderRequest setChannelExtra(String v) { this.channelExtra = v; return this; }
    public UnifiedOrderRequest setChannelExtra(Map<String, Object> v) {
        this.channelExtra = v == null ? null : JSON.toJSONString(v);
        return this;
    }
    public UnifiedOrderRequest setChannelExtra(Collection<?> v) {
        this.channelExtra = v == null ? null : JSON.toJSONString(v);
        return this;
    }
    public UnifiedOrderRequest setChannelExtra(JsonBuilder v) {
        this.channelExtra = v == null ? null : v.toJson();
        return this;
    }

    /** 扩展参数：字符串原样使用，Map/Collection 自动 JSON 化 */
    public UnifiedOrderRequest setExtParam(String v) { this.extParam = v; return this; }
    public UnifiedOrderRequest setExtParam(Map<String, Object> v) {
        this.extParam = v == null ? null : JSON.toJSONString(v);
        return this;
    }
    public UnifiedOrderRequest setExtParam(Collection<?> v) {
        this.extParam = v == null ? null : JSON.toJSONString(v);
        return this;
    }

    @Override
    public void validate() {
        required("mch_order_no", mchOrderNo, 30);
        required("way_code", wayCode, 30);
        if (!isAllowedWayCode(wayCode)) throw new IllegalArgumentException("way_code 必须是统一下单支持的支付代码");
        positive("amount", amount);
        if (currency == null || !currency.matches("[A-Za-z]{3}"))
            throw new IllegalArgumentException("currency 必须是 3 位字母货币代码");
        required("subject", subject, 64);
        required("body", body, 256);
        required("channel_extra", channelExtra, 256);
        optional("client_ip", clientIp, 32);
        optional("notify_url", notifyUrl, 128);
        optional("return_url", returnUrl, 128);
        optional("ext_param", extParam, 512);
        ipv4("client_ip", clientIp);
        httpUrl("notify_url", notifyUrl);
        httpUrl("return_url", returnUrl);
        if (pollingMode != null && (pollingMode < 0 || pollingMode > 3))
            throw new IllegalArgumentException("polling_mode 只允许 0、1、2、3");
        Map<String, Object> extra = parseObject(channelExtra, "channel_extra");
        String requiredExtra = null;
        boolean indirectPayData = extra.get("pay_data_type") != null
                && !String.valueOf(extra.get("pay_data_type")).trim().isEmpty();
        if (WayCodeEnum.ALI_LITE.equals(wayCode)) requiredExtra = "buyer_id";
        if (WayCodeEnum.WX_LITE.equals(wayCode)) requiredExtra = "openid";
        if (!indirectPayData && WayCodeEnum.ALI_PUB.equals(wayCode)) requiredExtra = "buyer_id";
        if (!indirectPayData && WayCodeEnum.WX_PUB.equals(wayCode)) requiredExtra = "openid";
        if (WayCodeEnum.WX_BAR.equals(wayCode) || WayCodeEnum.ALI_BAR.equals(wayCode) || WayCodeEnum.YSF_BAR.equals(wayCode))
            requiredExtra = "auth_code";
        if (requiredExtra != null && (extra.get(requiredExtra) == null || String.valueOf(extra.get(requiredExtra)).trim().isEmpty()))
            throw new IllegalArgumentException("channel_extra." + requiredExtra + " 必填");
        if (WayCodeEnum.BANK_FAST.equals(wayCode)) {
            required("channel_extra.channel_user", string(extra.get("channel_user")), 128);
            required("channel_extra.channel_card_id", string(extra.get("channel_card_id")), 128);
        }
        if (("1".equals(divisionMode) || "2".equals(divisionMode)) && !(extra.get("div_list") instanceof Collection))
            throw new IllegalArgumentException("分账时 channel_extra.div_list 必填");
    }

    protected boolean isAllowedWayCode(String value) {
        return WayCodeEnum.paymentMethods().containsKey(value);
    }

    private static String string(Object value) { return value == null ? null : String.valueOf(value); }
}
