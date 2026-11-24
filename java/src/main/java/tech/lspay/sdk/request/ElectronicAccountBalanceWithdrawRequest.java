package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.enums.WayCodeEnum; import tech.lspay.sdk.response.ElectronicAccountBalanceWithdrawResponse;
import java.math.BigDecimal;
public class ElectronicAccountBalanceWithdrawRequest extends BaseRequest {
 public String orderId,wayCode=WayCodeEnum.BALANCE_WITHDRAW,accountNo,withdrawAmount;
 @Override public String getFunctionCode(){return EndpointEnum.ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW;} @Override public Class<ElectronicAccountBalanceWithdrawResponse> getResponseClass(){return ElectronicAccountBalanceWithdrawResponse.class;}
 public ElectronicAccountBalanceWithdrawRequest setOrderId(String v){orderId=v;return this;} public ElectronicAccountBalanceWithdrawRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.BALANCE_WITHDRAW);wayCode=v;return this;}
 public ElectronicAccountBalanceWithdrawRequest setAccountNo(String v){accountNo=v;return this;} public ElectronicAccountBalanceWithdrawRequest setWithdrawAmount(String v){withdrawAmount=v;return this;}
 public ElectronicAccountBalanceWithdrawRequest setWithdrawAmount(Number v){withdrawAmount=v==null?null:String.valueOf(v);return this;}
 @Override public void validate(){required("order_id",orderId,30);fixed("way_code",wayCode,WayCodeEnum.BALANCE_WITHDRAW);required("account_no",accountNo,64);required("withdraw_amount",withdrawAmount,20);
  try{if(new BigDecimal(withdrawAmount).compareTo(BigDecimal.ZERO)<=0)throw new Exception();}catch(Exception e){throw new IllegalArgumentException("withdraw_amount 必须大于 0");}}
}
