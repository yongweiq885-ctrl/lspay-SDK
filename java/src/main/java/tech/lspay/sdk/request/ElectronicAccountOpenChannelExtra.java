package tech.lspay.sdk.request;
public final class ElectronicAccountOpenChannelExtra extends JsonBuilder {
 public ElectronicAccountOpenChannelExtra setUserInfo(ElectronicAccountUserInfo v){set("user_info",v);return this;}
 public ElectronicAccountOpenChannelExtra setMerchantInfo(ElectronicAccountMerchantInfo v){set("merchant_info",v);return this;}
}
