package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.response.ElectronicAccountUserQueryResponse;
public class ElectronicAccountUserQueryRequest extends BaseRequest {
 public String wayCode=tech.lspay.sdk.enums.WayCodeEnum.APPLY,accountNo,clientIp; @Override public String getFunctionCode(){return EndpointEnum.ELECTRONIC_ACCOUNT_USER_QUERY;}
 @Override public Class<ElectronicAccountUserQueryResponse> getResponseClass(){return ElectronicAccountUserQueryResponse.class;}
 public ElectronicAccountUserQueryRequest setWayCode(String v){fixed("way_code",v,tech.lspay.sdk.enums.WayCodeEnum.APPLY);wayCode=v;return this;}
 public ElectronicAccountUserQueryRequest setAccountNo(String v){accountNo=v;return this;} public ElectronicAccountUserQueryRequest setClientIp(String v){clientIp=v;return this;}
 @Override public void validate(){fixed("way_code",wayCode,tech.lspay.sdk.enums.WayCodeEnum.APPLY);required("account_no",accountNo,64);optional("client_ip",clientIp,32);ipv4("client_ip",clientIp);}
}
