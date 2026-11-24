package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.enums.WayCodeEnum; import tech.lspay.sdk.response.ElectronicAccountSettlementQueryResponse;
public class ElectronicAccountSettlementQueryRequest extends AbstractPagedAccountRequest {
 public String orderId,wayCode=WayCodeEnum.SETTLE_QUERY,accountNo,beginDate,endDate; public Integer pageSize,pageNum;
 @Override public String getFunctionCode(){return EndpointEnum.ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY;} @Override public Class<ElectronicAccountSettlementQueryResponse> getResponseClass(){return ElectronicAccountSettlementQueryResponse.class;}
 public ElectronicAccountSettlementQueryRequest setOrderId(String v){orderId=v;return this;} public ElectronicAccountSettlementQueryRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.SETTLE_QUERY);wayCode=v;return this;}
 public ElectronicAccountSettlementQueryRequest setAccountNo(String v){accountNo=v;return this;} public ElectronicAccountSettlementQueryRequest setBeginDate(String v){beginDate=v;return this;}
 public ElectronicAccountSettlementQueryRequest setEndDate(String v){endDate=v;return this;} public ElectronicAccountSettlementQueryRequest setPageSize(int v){pageSize=v;return this;} public ElectronicAccountSettlementQueryRequest setPageNum(int v){pageNum=v;return this;}
 @Override public void validate(){required("order_id",orderId,30);fixed("way_code",wayCode,WayCodeEnum.SETTLE_QUERY);required("account_no",accountNo,64);date("begin_date",beginDate);date("end_date",endDate);
  if(beginDate.compareTo(endDate)>0)throw new IllegalArgumentException("begin_date 不能晚于 end_date");page("page_size",pageSize);page("page_num",pageNum);}
}
