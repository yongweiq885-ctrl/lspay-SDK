package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.response.QuickPayBindApplyResponse;
import java.util.Map;
public class QuickPayBindApplyRequest extends BaseRequest {
    public String orderId,wayCode=WayCodeEnum.BANK_FAST,clientIp,channelExtra;
    @Override public String getFunctionCode(){return EndpointEnum.QUICK_PAY_BIND_APPLY;}
    @Override public Class<QuickPayBindApplyResponse> getResponseClass(){return QuickPayBindApplyResponse.class;}
    public QuickPayBindApplyRequest setOrderId(String v){orderId=v;return this;}
    public QuickPayBindApplyRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.BANK_FAST);wayCode=v;return this;}
    public QuickPayBindApplyRequest setClientIp(String v){clientIp=v;return this;}
    public QuickPayBindApplyRequest setChannelExtra(String v){channelExtra=jsonObject(v,"channel_extra");return this;}
    public QuickPayBindApplyRequest setChannelExtra(Map<String,Object> v){channelExtra=jsonObject(v,"channel_extra");return this;}
    public QuickPayBindApplyRequest setChannelExtra(QuickPayBindChannelExtra v){channelExtra=v==null?null:v.toJson();return this;}
    @Override public void validate(){required("order_id",orderId,30);fixed("way_code",wayCode,WayCodeEnum.BANK_FAST);
        optional("client_ip",clientIp,32);ipv4("client_ip",clientIp);required("channel_extra",channelExtra,256);}
}
