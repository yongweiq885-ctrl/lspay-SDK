package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse;
public abstract class ElectronicAccountResponse extends LsPayResponse {
 protected ElectronicAccountResponse(String raw){super(raw);}
 protected String accountValue(String key){
  Object value=getData().containsKey(key)?getData().get(key):getMap().get(key);
  return value==null?null:String.valueOf(value);
 }
 public String getTransStatus(){return accountValue("trans_status");}
 @Override public boolean isError(){
  return getMap().containsKey("status")||getMap().containsKey("code")||getMap().containsKey("retCode")
    ?super.isError():getTransStatus()==null||"F".equals(getTransStatus());
 }
 public boolean isSucceeded(){return isSuccessful();}
 public boolean isSuccessful(){return !isError()&&"S".equals(getTransStatus());}
 public boolean isProcessing(){return !isError()&&"P".equals(getTransStatus());}
}
