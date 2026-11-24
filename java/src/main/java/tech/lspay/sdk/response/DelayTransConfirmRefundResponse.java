package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse;
public class DelayTransConfirmRefundResponse extends LsPayResponse {
    public DelayTransConfirmRefundResponse(String raw){super(raw);}
    public Long getUnconfirmAmount(){return longValue("unconfirm_amount");}
    public Long getConfirmedAmount(){return longValue("confirmed_amount");}
    public String getConfirmOrderId(){return stringValue("confirm_order_id");}
    public String getConfirmRefundNo(){return stringValue("confirm_refund_no");}
    public String getTransStatus(){return stringValue("trans_status");}
    public String getErrCode(){return stringValue("err_code");}
    public String getErrMsg(){return stringValue("err_msg");}
    public boolean isAccepted(){return !isError();}
    public boolean isSuccessful(){return !isError()&&"S".equals(getTransStatus());}
}
