package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.enums.WayCodeEnum; import tech.lspay.sdk.response.ElectronicAccountSingleWithdrawQueryResponse;
public class ElectronicAccountSingleWithdrawQueryRequest extends AbstractPagedAccountRequest {
 public String wayCode=WayCodeEnum.WITHDRAW_SINGLE_QUERY,accountNo,withdrawDate,orderId;
 @Override public String getFunctionCode(){return EndpointEnum.ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY;} @Override public Class<ElectronicAccountSingleWithdrawQueryResponse> getResponseClass(){return ElectronicAccountSingleWithdrawQueryResponse.class;}
 public ElectronicAccountSingleWithdrawQueryRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.WITHDRAW_SINGLE_QUERY);wayCode=v;return this;}
 public ElectronicAccountSingleWithdrawQueryRequest setAccountNo(String v){accountNo=v;return this;} public ElectronicAccountSingleWithdrawQueryRequest setWithdrawDate(String v){withdrawDate=v;return this;}
 public ElectronicAccountSingleWithdrawQueryRequest setOrderId(String v){orderId=v;return this;}
 @Override public void validate(){fixed("way_code",wayCode,WayCodeEnum.WITHDRAW_SINGLE_QUERY);required("account_no",accountNo,64);date("withdraw_date",withdrawDate);required("order_id",orderId,30);}
}
