package tech.lspay.sdk.request;
public final class ElectronicAccountUserInfo extends JsonBuilder {
 public ElectronicAccountUserInfo(){set("cert_type","00");}
 public ElectronicAccountUserInfo setCertType(String v){set("cert_type",v);return this;} public ElectronicAccountUserInfo setName(String v){set("name",v);return this;}
 public ElectronicAccountUserInfo setCertNo(String v){set("cert_no",v);return this;} public ElectronicAccountUserInfo setCertValidityType(String v){set("cert_validity_type",v);return this;}
 public ElectronicAccountUserInfo setCertBeginDate(String v){set("cert_begin_date",v);return this;} public ElectronicAccountUserInfo setCertEndDate(String v){set("cert_end_date",v);return this;}
 public ElectronicAccountUserInfo setMobileNo(String v){set("mobile_no",v);return this;} public ElectronicAccountUserInfo setEmail(String v){set("email",v);return this;}
}
