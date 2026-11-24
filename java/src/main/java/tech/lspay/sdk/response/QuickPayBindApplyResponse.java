package tech.lspay.sdk.response;
public class QuickPayBindApplyResponse extends QuickPayResponse {
 public QuickPayBindApplyResponse(String raw){super(raw);}
 public String getTransId(){return quickValue("trans_id");} public String getOrderId(){return quickValue("order_id");}
 public String getRespDesc(){return quickValue("resp_desc");}
}
