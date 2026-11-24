package tech.lspay.sdk.request;

import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.response.DelayTransCreateTaskResponse;

public class DelayTransCreateTaskRequest extends BaseRequest {
    public String accountNo, accountName, orderNo, notifyUrl, remark;
    public Long amount;
    @Override public String getFunctionCode() { return EndpointEnum.DELAY_TRANS_CREATE_TASK; }
    @Override public Class<DelayTransCreateTaskResponse> getResponseClass() { return DelayTransCreateTaskResponse.class; }
    public DelayTransCreateTaskRequest setAccountNo(String v) { accountNo = v; return this; }
    public DelayTransCreateTaskRequest setAccountName(String v) { accountName = v; return this; }
    public DelayTransCreateTaskRequest setOrderNo(String v) { orderNo = v; return this; }
    public DelayTransCreateTaskRequest setAmount(long v) { amount = v; return this; }
    public DelayTransCreateTaskRequest setNotifyUrl(String v) { notifyUrl = v; return this; }
    public DelayTransCreateTaskRequest setRemark(String v) { remark = v; return this; }
    @Override public void validate() {
        required("account_no", accountNo, 30); required("account_name", accountName, 30);
        required("order_no", orderNo, 30); positive("amount", amount);
        optional("notify_url", notifyUrl, 64); httpUrl("notify_url", notifyUrl);
        optional("remark", remark, 256);
    }
}
