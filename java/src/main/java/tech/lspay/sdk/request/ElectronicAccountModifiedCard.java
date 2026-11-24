package tech.lspay.sdk.request;
public final class ElectronicAccountModifiedCard extends ElectronicAccountSettlementCard {
 @Override public ElectronicAccountModifiedCard setCardType(String v){super.setCardType(v);return this;}
 @Override public ElectronicAccountModifiedCard setCardName(String v){super.setCardName(v);return this;}
 @Override public ElectronicAccountModifiedCard setCardNo(String v){super.setCardNo(v);return this;}
 @Override public java.util.Map<String,Object> toMap(){for(String k:new String[]{"card_type","card_name","card_no"})
  if(!values.containsKey(k)||String.valueOf(values.get(k)).trim().isEmpty())throw new IllegalArgumentException("card_info."+k+" 必填");
  return new java.util.LinkedHashMap<String,Object>(values);}
 @Override public String toJson(){toMap();return com.alibaba.fastjson2.JSON.toJSONString(values);}
}
