package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse;
public class ElectronicAccountBalanceWithdrawResponse extends LsPayResponse {
 public ElectronicAccountBalanceWithdrawResponse(String raw){super(raw);} public String getAcceptanceMessage(){return stringValue("err_msg");}
 public String getAcceptanceStatus(){return stringValue("status");}
 public boolean isAccepted(){return !isError()&&("success".equalsIgnoreCase(getAcceptanceStatus())||"succeess".equalsIgnoreCase(getAcceptanceStatus()));}
}
