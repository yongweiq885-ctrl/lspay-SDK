package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse;
public class DelayTransConfirmResponse extends LsPayResponse {
    public DelayTransConfirmResponse(String raw) { super(raw); }
    public Long getUnconfirmAmount(){return longValue("unconfirm_amount");}
    public Long getConfirmedAmount(){return longValue("confirmed_amount");}
    public String getConfirmOrderId(){return stringValue("confirm_order_id");}
    public String getBatchOrderId(){return stringValue("batch_order_id");}
    public String getTransStatus(){return stringValue("trans_status");}
    public String getErrCode(){return stringValue("err_code");}
    public String getErrMsg(){return stringValue("err_msg");}
    public boolean isAccepted(){return !isError();}
    public boolean isSuccessful(){return !isError()&&"S".equals(getTransStatus());}
}
