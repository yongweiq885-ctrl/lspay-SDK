package tech.lspay.sdk.response;
public class QuickPayUnbindResponse extends QuickPayResponse {
 public QuickPayUnbindResponse(String raw){super(raw);} public String getOrderId(){return quickValue("order_id");}
 public String getRespDesc(){return quickValue("resp_desc");}
}
