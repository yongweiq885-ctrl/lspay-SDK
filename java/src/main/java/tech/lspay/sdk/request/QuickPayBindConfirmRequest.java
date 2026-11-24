package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.response.QuickPayBindConfirmResponse; import java.util.Map;
public class QuickPayBindConfirmRequest extends BaseRequest {
 public String orderId,wayCode=WayCodeEnum.BANK_FAST,clientIp,channelExtra,verifyCode;
 @Override public String getFunctionCode(){return EndpointEnum.QUICK_PAY_BIND_CONFIRM;}
 @Override public Class<QuickPayBindConfirmResponse> getResponseClass(){return QuickPayBindConfirmResponse.class;}
 public QuickPayBindConfirmRequest setOrderId(String v){orderId=v;return this;} public QuickPayBindConfirmRequest setClientIp(String v){clientIp=v;return this;}
 public QuickPayBindConfirmRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.BANK_FAST);wayCode=v;return this;}
 public QuickPayBindConfirmRequest setVerifyCode(String v){verifyCode=v;return this;}
 public QuickPayBindConfirmRequest setChannelExtra(String v){channelExtra=jsonObject(v,"channel_extra");return this;}
 public QuickPayBindConfirmRequest setChannelExtra(Map<String,Object> v){channelExtra=jsonObject(v,"channel_extra");return this;}
 public QuickPayBindConfirmRequest setChannelExtra(QuickPayBindConfirmChannelExtra v){channelExtra=v==null?null:v.toJson();return this;}
 @Override public void validate(){required("order_id",orderId,30);fixed("way_code",wayCode,WayCodeEnum.BANK_FAST);
  if(verifyCode==null||!verifyCode.matches("\\d{6}"))throw new IllegalArgumentException("verify_code 必须是 6 位数字");
  required("channel_extra",channelExtra,256);optional("client_ip",clientIp,32);ipv4("client_ip",clientIp);}
}
