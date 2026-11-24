package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse;
public abstract class QuickPayResponse extends LsPayResponse {
 protected QuickPayResponse(String raw){super(raw);}
 protected String quickValue(String key){Object v=getData().containsKey(key)?getData().get(key):getMap().get(key);return v==null?null:String.valueOf(v);}
 public String getTransStatus(){return quickValue("trans_status");}
 public boolean isSucceeded(){return isSuccessful();}
 public boolean isSuccessful(){return !isError()&&"S".equals(getTransStatus());}
 public boolean isProcessing(){return !isError()&&"P".equals(getTransStatus());}
 public boolean isAccepted(){return !isError();}
 @Override public boolean isError(){return getMap().containsKey("status")||getMap().containsKey("code")||getMap().containsKey("retCode")
   ?super.isError():getTransStatus()==null||"F".equals(getTransStatus());}
}
