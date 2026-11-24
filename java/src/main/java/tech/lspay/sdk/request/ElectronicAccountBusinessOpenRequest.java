package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.response.ElectronicAccountBusinessOpenResponse; import java.util.Map;
public class ElectronicAccountBusinessOpenRequest extends BaseRequest {
 public String wayCode=tech.lspay.sdk.enums.WayCodeEnum.APPLY,orderId,accountNo,clientIp,channelExtra;
 @Override public String getFunctionCode(){return EndpointEnum.ELECTRONIC_ACCOUNT_BUSINESS_OPEN;} @Override public Class<ElectronicAccountBusinessOpenResponse> getResponseClass(){return ElectronicAccountBusinessOpenResponse.class;}
 public ElectronicAccountBusinessOpenRequest setWayCode(String v){fixed("way_code",v,tech.lspay.sdk.enums.WayCodeEnum.APPLY);wayCode=v;return this;}
 public ElectronicAccountBusinessOpenRequest setOrderId(String v){orderId=v;return this;} public ElectronicAccountBusinessOpenRequest setAccountNo(String v){accountNo=v;return this;}
 public ElectronicAccountBusinessOpenRequest setClientIp(String v){clientIp=v;return this;} public ElectronicAccountBusinessOpenRequest setChannelExtra(String v){channelExtra=jsonObject(v,"channel_extra");return this;}
 public ElectronicAccountBusinessOpenRequest setChannelExtra(Map<String,Object> v){channelExtra=jsonObject(v,"channel_extra");return this;}
 public ElectronicAccountBusinessOpenRequest setChannelExtra(ElectronicAccountBusinessChannelExtra v){channelExtra=v==null?null:v.toJson();return this;}
 @Override public void validate(){fixed("way_code",wayCode,tech.lspay.sdk.enums.WayCodeEnum.APPLY);required("order_id",orderId,30);required("account_no",accountNo,32);optional("client_ip",clientIp,32);ipv4("client_ip",clientIp);
  required("channel_extra",channelExtra,4096);Map<String,Object> e=parseObject(channelExtra,"channel_extra");
  if(!e.containsKey("card_info")||!e.containsKey("settle_config"))throw new IllegalArgumentException("channel_extra.card_info/settle_config 必填");}
}
