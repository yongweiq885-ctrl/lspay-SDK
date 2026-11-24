package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.enums.WayCodeEnum; import tech.lspay.sdk.response.QuickPayUnbindResponse; import java.util.Map;
public class QuickPayUnbindRequest extends BaseRequest {
 public String orderId,wayCode=WayCodeEnum.BANK_FAST,clientIp,channelExtra;
 @Override public String getFunctionCode(){return EndpointEnum.QUICK_PAY_UNBIND;} @Override public Class<QuickPayUnbindResponse> getResponseClass(){return QuickPayUnbindResponse.class;}
 public QuickPayUnbindRequest setOrderId(String v){orderId=v;return this;} public QuickPayUnbindRequest setClientIp(String v){clientIp=v;return this;}
 public QuickPayUnbindRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.BANK_FAST);wayCode=v;return this;}
 public QuickPayUnbindRequest setChannelExtra(String v){channelExtra=jsonObject(v,"channel_extra");return this;}
 public QuickPayUnbindRequest setChannelExtra(Map<String,Object> v){channelExtra=jsonObject(v,"channel_extra");return this;}
 public QuickPayUnbindRequest setChannelExtra(QuickPayUnbindChannelExtra v){channelExtra=v==null?null:v.toJson();return this;}
 @Override public void validate(){required("order_id",orderId,30);fixed("way_code",wayCode,WayCodeEnum.BANK_FAST);required("channel_extra",channelExtra,256);
  optional("client_ip",clientIp,32);ipv4("client_ip",clientIp);}
}
