package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse;
import java.util.ArrayList; import java.util.List; import java.util.Map;
public class ElectronicAccountSettlementQueryResponse extends LsPayResponse {
 public ElectronicAccountSettlementQueryResponse(String raw){super(raw);}
 @SuppressWarnings("unchecked") public List<Map<String,Object>> getRecords(){
  Object data=getMap().get("data");
  if(data instanceof List)return maps((List<?>)data);
  if(!(data instanceof Map))return new ArrayList<>();
  Map<String,Object> object=(Map<String,Object>)data;
  for(String key:new String[]{"records","list","rows"}){
   Object records=object.get(key);if(records instanceof List)return maps((List<?>)records);
  }
  List<Map<String,Object>> result=new ArrayList<>();
  if(object.containsKey("trans_id"))result.add(object);
  return result;
 }
 @SuppressWarnings("unchecked") private List<Map<String,Object>> maps(List<?> source){
  List<Map<String,Object>> result=new ArrayList<>();
  for(Object item:source)if(item instanceof Map)result.add((Map<String,Object>)item);
  return result;
 }
 public Map<String,Object> getFirstRecord(){return getRecords().isEmpty()?null:getRecords().get(0);}
 private String first(String key){Object v=getFirstRecord()==null?null:getFirstRecord().get(key);return v==null?null:String.valueOf(v);}
 public String getTransId(){return first("trans_id");} public String getTransDate(){return first("trans_date");}
 public String getSettleCycle(){return first("settle_cycle");} public String getCardNo(){return first("card_no");}
 public String getCardName(){return first("card_name");} public String getBankCode(){return first("bank_code");}
 public String getTransAmount(){return first("trans_amt");} public String getFeeAmount(){return first("fee_amt");}
 public String getTransStatus(){return first("trans_stat");} public String getSettleType(){return first("settle_type");}
 public String getSettleAbstract(){return first("settle_abstract");} public String getSettleBatchNo(){return first("settle_batch_no");}
 public String getSettleDescription(){return first("settle_desc");}
}
