package tech.lspay.sdk.request;
public final class QuickPayUnbindChannelExtra extends JsonBuilder {
    public QuickPayUnbindChannelExtra setMchUserId(String v){set("mch_user_id",v);return this;}
    public QuickPayUnbindChannelExtra setChannelCardId(String v){set("channel_card_id",v);return this;}
    @Override public java.util.Map<String,Object> toMap(){
        for(String k:new String[]{"mch_user_id","channel_card_id"})
            if(!values.containsKey(k)||String.valueOf(values.get(k)).trim().isEmpty())throw new IllegalArgumentException("channel_extra."+k+" 必填");
        return super.toMap();
    }
    @Override public String toJson(){toMap();return super.toJson();}
}
