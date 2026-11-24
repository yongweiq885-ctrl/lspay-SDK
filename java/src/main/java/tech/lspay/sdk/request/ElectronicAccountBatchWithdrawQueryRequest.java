package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.enums.WayCodeEnum; import tech.lspay.sdk.response.ElectronicAccountBatchWithdrawQueryResponse;
public class ElectronicAccountBatchWithdrawQueryRequest extends AbstractPagedAccountRequest {
 public String wayCode=WayCodeEnum.WITHDRAW_QUERY,accountNo,beginDate,endDate; public Integer pageSize,pageNum;
 @Override public String getFunctionCode(){return EndpointEnum.ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY;} @Override public Class<ElectronicAccountBatchWithdrawQueryResponse> getResponseClass(){return ElectronicAccountBatchWithdrawQueryResponse.class;}
 public ElectronicAccountBatchWithdrawQueryRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.WITHDRAW_QUERY);wayCode=v;return this;}
 public ElectronicAccountBatchWithdrawQueryRequest setAccountNo(String v){accountNo=v;return this;} public ElectronicAccountBatchWithdrawQueryRequest setBeginDate(String v){beginDate=v;return this;}
 public ElectronicAccountBatchWithdrawQueryRequest setEndDate(String v){endDate=v;return this;} public ElectronicAccountBatchWithdrawQueryRequest setPageSize(int v){pageSize=v;return this;}
 public ElectronicAccountBatchWithdrawQueryRequest setPageNum(int v){pageNum=v;return this;}
 @Override public void validate(){fixed("way_code",wayCode,WayCodeEnum.WITHDRAW_QUERY);required("account_no",accountNo,64);date("begin_date",beginDate);date("end_date",endDate);
  if(beginDate.compareTo(endDate)>0)throw new IllegalArgumentException("begin_date 不能晚于 end_date");page("page_size",pageSize);page("page_num",pageNum);}
}
