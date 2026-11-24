package tech.lspay.sdk.request;
public final class ElectronicAccountCashConfig extends JsonBuilder {
 public ElectronicAccountCashConfig setCashType(String v){set("cash_type",v);return this;}
 public ElectronicAccountCashConfig setSwitchState(String v){set("switch_state",v);return this;} public ElectronicAccountCashConfig setSwitchState(int v){set("switch_state",v);return this;}
 public ElectronicAccountCashConfig setOutFeeFlag(String v){set("out_fee_flag",v);return this;} public ElectronicAccountCashConfig setOutFeeFlag(int v){set("out_fee_flag",v);return this;}
 @Override public java.util.Map<String,Object> toMap(){String type=String.valueOf(values.get("cash_type"));
  if(!java.util.Arrays.asList("D0","D1","DM").contains(type))throw new IllegalArgumentException("cash_type 非法");
  for(String k:new String[]{"switch_state","out_fee_flag"}){String v=String.valueOf(values.get(k));if(!"1".equals(v)&&!"2".equals(v))throw new IllegalArgumentException(k+" 只允许 1 或 2");}
  return super.toMap();}
 @Override public String toJson(){toMap();return super.toJson();}
}
