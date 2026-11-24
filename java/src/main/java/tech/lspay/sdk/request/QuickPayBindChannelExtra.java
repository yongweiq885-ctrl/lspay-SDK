package tech.lspay.sdk.request;

public final class QuickPayBindChannelExtra extends JsonBuilder {
    public QuickPayBindChannelExtra(){set("cert_type","00");}
    public QuickPayBindChannelExtra setMchUserId(String v){set("mch_user_id",v);return this;}
    public QuickPayBindChannelExtra setCertType(String v){set("cert_type",v);return this;}
    public QuickPayBindChannelExtra setCardId(String v){set("card_id",v);return this;}
    public QuickPayBindChannelExtra setCardName(String v){set("card_name",v);return this;}
    public QuickPayBindChannelExtra setCertId(String v){set("cert_id",v);return this;}
    public QuickPayBindChannelExtra setCertValidityType(String v){set("cert_validity_type",v);return this;}
    public QuickPayBindChannelExtra setCertBeginDate(String v){set("cert_begin_date",v);return this;}
    public QuickPayBindChannelExtra setCertEndDate(String v){set("cert_end_date",v);return this;}
    public QuickPayBindChannelExtra setCardMp(String v){set("card_mp",v);return this;}
    public QuickPayBindChannelExtra setVipCode(String v){set("vip_code",v);return this;}
    public QuickPayBindChannelExtra setExpiration(String v){set("expiration",v);return this;}
    public QuickPayBindChannelExtra setDcType(String v){set("dc_type",v==null?null:v.toUpperCase());return this;}
    @Override public java.util.Map<String,Object> toMap(){
        for(String k:new String[]{"mch_user_id","card_id","card_name","cert_id","cert_validity_type","cert_begin_date","card_mp","dc_type"})
            if(!values.containsKey(k)||String.valueOf(values.get(k)).trim().isEmpty())throw new IllegalArgumentException("channel_extra."+k+" 必填");
        String type=String.valueOf(values.get("cert_validity_type"));
        if(!"0".equals(type)&&!"1".equals(type))throw new IllegalArgumentException("cert_validity_type 只允许 0 或 1");
        if("0".equals(type)&&!values.containsKey("cert_end_date"))throw new IllegalArgumentException("cert_end_date 必填");
        String dc=String.valueOf(values.get("dc_type"));
        if(!"CDSU".contains(dc)||dc.length()!=1)throw new IllegalArgumentException("dc_type 非法");
        return super.toMap();
    }
    @Override public String toJson(){toMap();return super.toJson();}
}
