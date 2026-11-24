package tech.lspay.sdk.request;
public final class ElectronicAccountSettleConfig extends JsonBuilder {
 public ElectronicAccountSettleConfig setSettleCycle(String v){set("settle_cycle",v);return this;}
 public ElectronicAccountSettleConfig setSettleStatus(String v){set("settle_status",v);return this;} public ElectronicAccountSettleConfig setSettleStatus(int v){set("settle_status",v);return this;}
 @Override public java.util.Map<String,Object> toMap(){String cycle=String.valueOf(values.get("settle_cycle"));if(!"T1".equals(cycle)&&!"D1".equals(cycle))throw new IllegalArgumentException("settle_cycle 只允许 T1/D1");
  String status=String.valueOf(values.get("settle_status"));if(!"1".equals(status)&&!"2".equals(status))throw new IllegalArgumentException("settle_status 只允许 1/2");return super.toMap();}
 @Override public String toJson(){toMap();return super.toJson();}
}
