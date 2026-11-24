package tech.lspay.sdk.response;
public class QuickPayConfirmResponse extends QuickPayResponse {
 public QuickPayConfirmResponse(String raw){super(raw);} public String getErrMsg(){return quickValue("err_msg");}
}
