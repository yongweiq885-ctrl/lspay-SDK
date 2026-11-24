package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.enums.WayCodeEnum; import tech.lspay.sdk.response.QuickPayConfirmResponse;
public class QuickPayConfirmRequest extends BaseRequest {
 public String wayCode=WayCodeEnum.BANK_FAST,clientIp,payOrderId,mchOrderNo,verifyCode;
 @Override public String getFunctionCode(){return EndpointEnum.QUICK_PAY_CONFIRM;}
 @Override public Class<? extends QuickPayConfirmResponse> getResponseClass(){return QuickPayConfirmResponse.class;}
 public QuickPayConfirmRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.BANK_FAST);wayCode=v;return this;}
 public QuickPayConfirmRequest setClientIp(String v){clientIp=v;return this;} public QuickPayConfirmRequest setPayOrderId(String v){payOrderId=v;return this;}
 public QuickPayConfirmRequest setMchOrderNo(String v){mchOrderNo=v;return this;} public QuickPayConfirmRequest setVerifyCode(String v){verifyCode=v;return this;}
 @Override public void validate(){fixed("way_code",wayCode,WayCodeEnum.BANK_FAST);optional("client_ip",clientIp,32);ipv4("client_ip",clientIp);
  if((payOrderId==null||payOrderId.isEmpty())&&(mchOrderNo==null||mchOrderNo.isEmpty()))throw new IllegalArgumentException("pay_order_id 与 mch_order_no 至少填写一个");
  optional("pay_order_id",payOrderId,30);optional("mch_order_no",mchOrderNo,30);
  if(verifyCode==null||!verifyCode.matches("\\d{6}"))throw new IllegalArgumentException("verify_code 必须是 6 位数字");}
}
