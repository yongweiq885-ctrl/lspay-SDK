package tech.lspay.sdk.response;

import tech.lspay.sdk.core.LsPayResponse;

public class UnifiedOrderResponse extends LsPayResponse {
    public UnifiedOrderResponse(String raw) { super(raw); }
    public String getPayOrderId() { return stringValue("pay_order_id"); }
    public String getMchOrderNo() { return stringValue("mch_order_no"); }
    public Long getAmount() { return longValue("amount"); }
    public Long getOrderState() { return longValue("order_state") != null ? longValue("order_state") : longValue("state"); }
    public String getPayInfo() { return stringValue("pay_info"); }
    public String getTransStatus() { return stringValue("trans_status"); }
    public String getErrCode() { return stringValue("err_code"); }
    public String getErrMsg() { return stringValue("err_msg"); }
    public boolean isAccepted() { return !isError() && !"F".equals(getTransStatus()); }
    public boolean isPaid() { return Long.valueOf(2L).equals(getOrderState()); }
}
