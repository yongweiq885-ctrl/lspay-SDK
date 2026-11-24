package tech.lspay.sdk.request;

import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.response.RefundResponse;

import java.util.Collection;
import java.util.Map;

/** 申请退款 */
public class RefundRequest extends BaseRequest {

    public String mchRefundNo;
    public String mchOrderNo;
    public String payOrderId;
    public Long   refundAmount;
    public Long   payAmount;
    public String currency = "cny";
    public String refundReason;
    public String clientIp;
    public String notifyUrl;
    public String channelExtra;
    public String extParam;

    @Override
    public String getFunctionCode() { return EndpointEnum.REFUND; }
    @Override public Class<RefundResponse> getResponseClass() { return RefundResponse.class; }

    public RefundRequest setMchRefundNo(String v)  { this.mchRefundNo = v;  return this; }
    public RefundRequest setMchOrderNo(String v)   { this.mchOrderNo = v;   return this; }
    public RefundRequest setPayOrderId(String v)   { this.payOrderId = v;   return this; }
    public RefundRequest setRefundAmount(long v)   { this.refundAmount = v; return this; }
    public RefundRequest setPayAmount(long v)      { this.payAmount = v;    return this; }
    public RefundRequest setCurrency(String v)     { this.currency = v; return this; }
    public RefundRequest setRefundReason(String v) { this.refundReason = v; return this; }
    public RefundRequest setClientIp(String v)     { this.clientIp = v; return this; }
    public RefundRequest setNotifyUrl(String v)    { this.notifyUrl = v;    return this; }
    public RefundRequest setChannelExtra(String v) { this.channelExtra = v; return this; }
    public RefundRequest setChannelExtra(Map<String, Object> v) { this.channelExtra = jsonObject(v, "channel_extra"); return this; }
    public RefundRequest setExtParam(String v) { this.extParam = v; return this; }
    public RefundRequest setExtParam(Map<String, Object> v) { this.extParam = jsonValue(v); return this; }
    public RefundRequest setExtParam(Collection<?> v) { this.extParam = jsonValue(v); return this; }

    @Override public void validate() {
        required("mch_refund_no", mchRefundNo, 30);
        if ((mchOrderNo == null || mchOrderNo.trim().isEmpty())
                && (payOrderId == null || payOrderId.trim().isEmpty()))
            throw new IllegalArgumentException("mch_order_no 与 pay_order_id 至少填写一个");
        optional("mch_order_no", mchOrderNo, 30);
        optional("pay_order_id", payOrderId, 30);
        positive("refund_amount", refundAmount);
        required("refund_reason", refundReason, 64);
        if (currency == null || !currency.matches("[A-Za-z]{3}"))
            throw new IllegalArgumentException("currency 必须是 3 位字母货币代码");
        optional("client_ip", clientIp, 32);
        ipv4("client_ip", clientIp);
        optional("notify_url", notifyUrl, 128);
        httpUrl("notify_url", notifyUrl);
        optional("channel_extra", channelExtra, 256);
        optional("ext_param", extParam, 512);
    }
}
