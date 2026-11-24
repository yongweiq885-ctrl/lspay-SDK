package tech.lspay.sdk.request;
public class ElectronicAccountSettlementCard extends JsonBuilder {
 public ElectronicAccountSettlementCard setCardType(String v){set("card_type",v);return this;} public ElectronicAccountSettlementCard setCardName(String v){set("card_name",v);return this;}
 public ElectronicAccountSettlementCard setCardNo(String v){set("card_no",v);return this;} public ElectronicAccountSettlementCard setProvId(String v){set("prov_id",v);return this;}
 public ElectronicAccountSettlementCard setAreaId(String v){set("area_id",v);return this;} public ElectronicAccountSettlementCard setBankCode(String v){set("bank_code",v);return this;}
 public ElectronicAccountSettlementCard setBranchCode(String v){set("branch_code",v);return this;} public ElectronicAccountSettlementCard setBranchName(String v){set("branch_name",v);return this;}
 public ElectronicAccountSettlementCard setCertType(String v){set("cert_type",v);return this;} public ElectronicAccountSettlementCard setCertNo(String v){set("cert_no",v);return this;}
 public ElectronicAccountSettlementCard setCertValidityType(String v){set("cert_validity_type",v);return this;} public ElectronicAccountSettlementCard setCertBeginDate(String v){set("cert_begin_date",v);return this;}
 public ElectronicAccountSettlementCard setCertEndDate(String v){set("cert_end_date",v);return this;} public ElectronicAccountSettlementCard setMobile(String v){set("mp",v);return this;}
 @Override public java.util.Map<String,Object> toMap(){
  for(String k:new String[]{"card_type","card_name","card_no","prov_id","area_id","mp"})if(!values.containsKey(k)||String.valueOf(values.get(k)).trim().isEmpty())throw new IllegalArgumentException("card_info."+k+" 必填");
  for(String k:new String[]{"prov_id","area_id"})if(!String.valueOf(values.get(k)).matches("\\d{6}"))throw new IllegalArgumentException("card_info."+k+" 必须是 6 位数字");
  String type=String.valueOf(values.get("card_type"));
  if("0".equals(type)){if(!values.containsKey("bank_code"))throw new IllegalArgumentException("对公卡 bank_code 必填");
   if(!values.containsKey("branch_code")&&!values.containsKey("branch_name"))throw new IllegalArgumentException("对公卡支行信息必填");
  }else if("1".equals(type)){for(String k:new String[]{"cert_type","cert_no","cert_validity_type","cert_begin_date"})
    if(!values.containsKey(k)||String.valueOf(values.get(k)).isEmpty())throw new IllegalArgumentException("对私卡 "+k+" 必填");
   if("0".equals(String.valueOf(values.get("cert_validity_type")))&&!values.containsKey("cert_end_date"))throw new IllegalArgumentException("cert_end_date 必填");
  }else throw new IllegalArgumentException("card_type 只允许 0 或 1");
  return super.toMap();
 }
 @Override public String toJson(){toMap();return super.toJson();}
}
