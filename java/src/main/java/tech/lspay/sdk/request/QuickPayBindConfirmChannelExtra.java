package tech.lspay.sdk.request;
public final class QuickPayBindConfirmChannelExtra extends JsonBuilder {
    public QuickPayBindConfirmChannelExtra(){set("cert_type","00");}
    public QuickPayBindConfirmChannelExtra setMchUserId(String v){set("mch_user_id",v);return this;}
    public QuickPayBindConfirmChannelExtra setCertType(String v){set("cert_type",v);return this;}
    public QuickPayBindConfirmChannelExtra setCardId(String v){set("card_id",v);return this;}
    public QuickPayBindConfirmChannelExtra setCardName(String v){set("card_name",v);return this;}
    public QuickPayBindConfirmChannelExtra setCertId(String v){set("cert_id",v);return this;}
    public QuickPayBindConfirmChannelExtra setCardMp(String v){set("card_mp",v);return this;}
    public QuickPayBindConfirmChannelExtra setDcType(String v){set("dc_type",v==null?null:v.toUpperCase());return this;}
    @Override public java.util.Map<String,Object> toMap(){
        for(String k:new String[]{"mch_user_id","card_id","card_name","cert_id","card_mp","dc_type"})
            if(!values.containsKey(k)||String.valueOf(values.get(k)).trim().isEmpty())throw new IllegalArgumentException("channel_extra."+k+" 必填");
        return super.toMap();
    }
    @Override public String toJson(){toMap();return super.toJson();}
}
