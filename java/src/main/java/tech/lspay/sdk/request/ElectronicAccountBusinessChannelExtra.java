package tech.lspay.sdk.request;
public final class ElectronicAccountBusinessChannelExtra extends JsonBuilder {
 public ElectronicAccountBusinessChannelExtra setCardInfo(ElectronicAccountSettlementCard v){set("card_info",v);return this;}
 public ElectronicAccountBusinessChannelExtra setSettleConfig(ElectronicAccountSettleConfig v){set("settle_config",v);return this;}
 @SuppressWarnings("unchecked") public ElectronicAccountBusinessChannelExtra addCashConfig(ElectronicAccountCashConfig v){
  java.util.List<Object> list=(java.util.List<Object>)values.get("cash_config");if(list==null){list=new java.util.ArrayList<>();values.put("cash_config",list);}
  list.add(v.toMap());return this;
 }
}
