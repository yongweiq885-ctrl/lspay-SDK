package tech.lspay.sdk.request;

import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.response.DelayTransConfirmRefundResponse;

import java.util.Map;

public class DelayTransConfirmRefundRequest extends BaseRequest {
    public String confirmOrderId, confirmRefundNo, channelExtra;
    @Override public String getFunctionCode() { return EndpointEnum.DELAY_TRANS_CONFIRM_REFUND; }
    @Override public Class<DelayTransConfirmRefundResponse> getResponseClass() { return DelayTransConfirmRefundResponse.class; }
    public DelayTransConfirmRefundRequest setConfirmOrderId(String v) { confirmOrderId = v; return this; }
    public DelayTransConfirmRefundRequest setConfirmRefundNo(String v) { confirmRefundNo = v; return this; }
    public DelayTransConfirmRefundRequest setChannelExtra(String v) { channelExtra = jsonObject(v, "channel_extra"); return this; }
    public DelayTransConfirmRefundRequest setChannelExtra(Map<String, Object> v) { channelExtra = jsonObject(v, "channel_extra"); return this; }
    @Override public void validate() {
        required("confirm_order_id", confirmOrderId, 30);
        required("confirm_refund_no", confirmRefundNo, 30);
        optional("channel_extra", channelExtra, 256);
    }
}
