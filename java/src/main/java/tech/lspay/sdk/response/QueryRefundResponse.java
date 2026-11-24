package tech.lspay.sdk.response;

public class QueryRefundResponse extends RefundResponse {
    public QueryRefundResponse(String raw) { super(raw); }
    public String getPayOrderId() { return stringValue("pay_order_id"); }
    public String getMchNo() { return stringValue("mch_no"); }
    public String getAppId() { return stringValue("app_id"); }
    public String getCurrency() { return stringValue("currency"); }
    public String getExtParam() { return stringValue("ext_param"); }
    /** 10 位秒级时间戳。 */
    public Long getAddTime() { return longValue("add_time"); }
    /** 10 位秒级时间戳。 */
    public Long getSuccessTime() { return longValue("success_time"); }
}
