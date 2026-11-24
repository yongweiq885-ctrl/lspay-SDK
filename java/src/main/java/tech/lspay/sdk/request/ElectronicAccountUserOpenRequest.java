package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.enums.WayCodeEnum; import tech.lspay.sdk.response.ElectronicAccountUserOpenResponse; import java.util.Map;
public class ElectronicAccountUserOpenRequest extends BaseRequest {
 public String wayCode=WayCodeEnum.APPLY,orderId,accountName,relationType,relationTypeName,accountAlias,divisionProfit,clientIp,channelExtra;
 public Integer accountType;
 @Override public String getFunctionCode(){return EndpointEnum.ELECTRONIC_ACCOUNT_USER_OPEN;} @Override public Class<ElectronicAccountUserOpenResponse> getResponseClass(){return ElectronicAccountUserOpenResponse.class;}
 public ElectronicAccountUserOpenRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.APPLY);wayCode=v;return this;} public ElectronicAccountUserOpenRequest setOrderId(String v){orderId=v;return this;}
 public ElectronicAccountUserOpenRequest setAccountName(String v){accountName=v;return this;} public ElectronicAccountUserOpenRequest setAccountType(int v){accountType=v;return this;}
 public ElectronicAccountUserOpenRequest setRelationType(String v){relationType=v;return this;} public ElectronicAccountUserOpenRequest setRelationTypeName(String v){relationTypeName=v;return this;}
 public ElectronicAccountUserOpenRequest setAccountAlias(String v){accountAlias=v;return this;} public ElectronicAccountUserOpenRequest setDivisionProfit(String v){divisionProfit=v;return this;}
 public ElectronicAccountUserOpenRequest setDivisionProfit(Number v){divisionProfit=v==null?null:String.valueOf(v);return this;} public ElectronicAccountUserOpenRequest setClientIp(String v){clientIp=v;return this;}
 public ElectronicAccountUserOpenRequest setChannelExtra(String v){channelExtra=jsonObject(v,"channel_extra");return this;} public ElectronicAccountUserOpenRequest setChannelExtra(Map<String,Object> v){channelExtra=jsonObject(v,"channel_extra");return this;}
 public ElectronicAccountUserOpenRequest setChannelExtra(ElectronicAccountOpenChannelExtra v){channelExtra=v==null?null:v.toJson();return this;}
 @Override public void validate(){fixed("way_code",wayCode,WayCodeEnum.APPLY);required("order_id",orderId,30);required("account_name",accountName,128);
  if(accountType==null||accountType<1||accountType>3)throw new IllegalArgumentException("account_type 只允许 1、2、3");required("relation_type",relationType,32);
  if(!java.util.Arrays.asList("SERVICE_PROVIDER","STORE","STAFF","STORE_OWNER","PARTNER","HEADQUARTER","BRAND","DISTRIBUTOR","USER","SUPPLIER","CUSTOM").contains(relationType))
   throw new IllegalArgumentException("relation_type 非法");
  if("CUSTOM".equals(relationType))required("relation_type_name",relationTypeName,64);required("account_alias",accountAlias,64);required("division_profit",divisionProfit,16);
  try{double d=Double.parseDouble(divisionProfit);if(d<0||d>1)throw new Exception();}catch(Exception e){throw new IllegalArgumentException("division_profit 必须在 0-1");}
  optional("client_ip",clientIp,32);ipv4("client_ip",clientIp);required("channel_extra",channelExtra,4096);
  Map<String,Object> extra=parseObject(channelExtra,"channel_extra");if(accountType==1&&!extra.containsKey("user_info"))throw new IllegalArgumentException("个人账户 user_info 必填");
  if(accountType!=1&&!extra.containsKey("merchant_info"))throw new IllegalArgumentException("企业账户 merchant_info 必填");}
}
