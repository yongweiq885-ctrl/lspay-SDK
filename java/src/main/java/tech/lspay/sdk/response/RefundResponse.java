package tech.lspay.sdk.response;

import tech.lspay.sdk.core.LsPayResponse;

public class RefundResponse extends LsPayResponse {
    public RefundResponse(String raw) { super(raw); }
    public String getRefundOrderId() {
        String value = stringValue("refund_order_id");
        if (value == null) value = stringValue("order_refund_id");
        return value == null ? stringValue("order_refund_Id") : value;
    }
    public String getOrderRefundId() { return getRefundOrderId(); }
    public String getMchRefundNo() { return stringValue("mch_refund_no"); }
    public String getChannelOrderNo() {
        String value = stringValue("channel_order_no");
        return value == null ? stringValue("channel_rrder_no") : value;
    }
    public String getErrCode() { return stringValue("err_code"); }
    public String getErrMsg() { return stringValue("err_msg"); }
    public Long getState() { return longValue("state"); }
    public Long getPayAmount() { return longValue("pay_amount"); }
    public Long getRefundAmount() { return longValue("refund_amount"); }
    public boolean isAccepted() { return !isError(); }
    public boolean isRefunded() { return !isError() && Long.valueOf(2L).equals(getState()); }
}
