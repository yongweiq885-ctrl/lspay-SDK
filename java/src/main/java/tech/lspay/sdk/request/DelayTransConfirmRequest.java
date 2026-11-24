package tech.lspay.sdk.request;

import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.response.DelayTransConfirmResponse;

import java.util.Map;

public class DelayTransConfirmRequest extends BaseRequest {
    public String payOrderId, mchOrderNo, orderId, channelExtra;
    @Override public String getFunctionCode() { return EndpointEnum.DELAY_TRANS_CONFIRM; }
    @Override public Class<DelayTransConfirmResponse> getResponseClass() { return DelayTransConfirmResponse.class; }
    public DelayTransConfirmRequest setPayOrderId(String v) { payOrderId = v; return this; }
    public DelayTransConfirmRequest setMchOrderNo(String v) { mchOrderNo = v; return this; }
    public DelayTransConfirmRequest setOrderId(String v) { orderId = v; return this; }
    public DelayTransConfirmRequest setChannelExtra(String v) { channelExtra = jsonObject(v, "channel_extra"); return this; }
    public DelayTransConfirmRequest setChannelExtra(Map<String, Object> v) { channelExtra = jsonObject(v, "channel_extra"); return this; }
    public DelayTransConfirmRequest setChannelExtra(DivisionChannelExtra v) { channelExtra = v == null ? null : v.toJson(); return this; }
    @Override public void validate() {
        optional("pay_order_id", payOrderId, 30); optional("mch_order_no", mchOrderNo, 30);
        if ((payOrderId == null || payOrderId.isEmpty()) && (mchOrderNo == null || mchOrderNo.isEmpty()))
            throw new IllegalArgumentException("pay_order_id 与 mch_order_no 至少填写一个");
        required("order_id", orderId, 30); optional("channel_extra", channelExtra, 256);
    }
}
