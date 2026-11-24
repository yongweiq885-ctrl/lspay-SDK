package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse; import java.util.ArrayList; import java.util.List; import java.util.Map;
public class ElectronicAccountBatchWithdrawQueryResponse extends LsPayResponse {
 public ElectronicAccountBatchWithdrawQueryResponse(String raw){super(raw);} public Long getCount(){return longValue("count");}
 public Long getPageSize(){return longValue("page_size");} public Long getPageNum(){return longValue("page_num");}
 public String getChannelStatus(){return stringValue("status");}
 @SuppressWarnings("unchecked") public List<Map<String,Object>> getRecords(){
  Object records=getData().get("data");if(!(records instanceof List))return new ArrayList<>();
  List<Map<String,Object>> result=new ArrayList<>();for(Object item:(List<?>)records)if(item instanceof Map)result.add((Map<String,Object>)item);return result;
 }
 public Map<String,Object> getFirstRecord(){return getRecords().isEmpty()?null:getRecords().get(0);}
 private String first(String k){Object v=getFirstRecord()==null?null:getFirstRecord().get(k);return v==null?null:String.valueOf(v);}
 public String getSettleDate(){return first("settle_date");} public String getCardNo(){return first("card_no");}
 public String getCardName(){return first("card_name");} public String getBankName(){return first("bank_name");}
 public String getBatchTransType(){return first("batch_trans_type");}
 public String getCashAmount(){return first("cash_amt");} public String getFeeAmount(){return first("fee_amt");}
 public String getFeeFlag(){return first("fee_flag");} public String getOriginalRequestDate(){return first("org_req_date");}
 public String getOrderId(){return first("order_id");} public String getTransStatus(){return first("trans_status");}
 public String getRemark(){return first("remark");} public String getTransDescription(){return first("trans_desc");}
 public boolean isFirstRecordPending(){return !isError()&&"pending".equals(getTransStatus());}
 public boolean isFirstRecordFailed(){return !isError()&&"failed".equals(getTransStatus());}
 public boolean isFirstRecordSucceeded(){return !isError()&&"succeeded".equals(getTransStatus());}
 public boolean isChannelSuccessful(){return "success".equalsIgnoreCase(getChannelStatus())||"succeess".equalsIgnoreCase(getChannelStatus());}
}
