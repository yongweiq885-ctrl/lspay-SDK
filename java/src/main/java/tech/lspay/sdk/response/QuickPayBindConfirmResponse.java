package tech.lspay.sdk.response;
public class QuickPayBindConfirmResponse extends QuickPayResponse {
 public QuickPayBindConfirmResponse(String raw){super(raw);}
 public String getChannelUser(){return quickValue("channel_user");} public String getChannelCardId(){return quickValue("channel_card_id");}
 public String getRespDesc(){return quickValue("resp_desc");}
}
