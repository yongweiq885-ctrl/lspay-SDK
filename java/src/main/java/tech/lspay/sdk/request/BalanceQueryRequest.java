package tech.lspay.sdk.request;

import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.response.BalanceQueryResponse;

public class BalanceQueryRequest extends BaseRequest {
    public String orderId, wayCode = WayCodeEnum.BALANCE_QUERY, clientIp, accountNo;
    @Override public String getFunctionCode(){return EndpointEnum.BALANCE_QUERY;}
    @Override public Class<BalanceQueryResponse> getResponseClass(){return BalanceQueryResponse.class;}
    public BalanceQueryRequest setOrderId(String v){orderId=v;return this;}
    public BalanceQueryRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.BALANCE_QUERY);wayCode=v;return this;}
    public BalanceQueryRequest setClientIp(String v){clientIp=v;return this;}
    public BalanceQueryRequest setAccountNo(String v){accountNo=v;return this;}
    @Override public void validate(){
        required("order_id",orderId,30);fixed("way_code",wayCode,WayCodeEnum.BALANCE_QUERY);
        required("account_no",accountNo,64);optional("client_ip",clientIp,32);ipv4("client_ip",clientIp);
    }
}
