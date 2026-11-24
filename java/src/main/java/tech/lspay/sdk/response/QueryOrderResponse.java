package tech.lspay.sdk.response;

import tech.lspay.sdk.core.LsPayResponse;

public class QueryOrderResponse extends LsPayResponse {
    public QueryOrderResponse(String raw) { super(raw); }
    public String getPayOrderId() { return stringValue("pay_order_id"); }
    public String getMchNo() { return stringValue("mch_no"); }
    public String getAppId() { return stringValue("app_id"); }
    public String getMchOrderNo() { return stringValue("mch_order_no"); }
    public String getWayCode() { return stringValue("way_code"); }
    public String getCurrency() { return stringValue("currency"); }
    public String getClientIp() { return stringValue("client_ip"); }
    public String getSubject() { return stringValue("subject"); }
    public String getBody() { return stringValue("body"); }
    public String getChannelOrderNo() { return stringValue("channel_order_no"); }
    public String getExtParam() { return stringValue("ext_param"); }
    public String getFundFreezeStatus() { return stringValue("fund_freeze_stat"); }
    public String getFreezeTime() { return stringValue("freeze_time"); }
    public String getUnfreezeTime() { return stringValue("unfreeze_time"); }
    public Long getAmount() { return longValue("amount"); }
    public Long getState() { return longValue("state"); }
    public Long getOrderState() { return getState(); }
    public Long getUnconfirmAmount() { return longValue("unconfirm_amount"); }
    public Long getConfirmedAmount() { return longValue("confirmed_amount"); }
    public Long getUnfreezeAmount() { return longValue("unfreeze_amt"); }
    /** 10 位秒级时间戳。 */
    public Long getAddTime() { return longValue("add_time"); }
    /** 10 位秒级时间戳。 */
    public Long getSuccessTime() { return longValue("success_time"); }
    public Long getDivisionMode() { return longValue("division_mode"); }
    public Long getDivisionState() { return longValue("division_state"); }
    public String getErrCode() { return stringValue("err_code"); }
    public String getErrMsg() { return stringValue("err_msg"); }
}
