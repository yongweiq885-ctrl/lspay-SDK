package tech.lspay.sdk.notify;
import tech.lspay.sdk.config.MerConfig; import tech.lspay.sdk.core.LsPay; import java.util.Map;
public final class LsPayNotify {
 public static final String SUCCESS_RESPONSE="ok",FAILURE_RESPONSE="fail"; private LsPayNotify(){}
 public static BaseNotify parse(Map<String,?> data){return parse(data,"default");}
 public static BaseNotify parse(Map<String,?> data,String profile){
  String notify=string(data,"notify_type"),event=string(data,"event_type");
  if("refund".equals(notify)||(event!=null&&event.startsWith("refund.")))return parseRefund(data,profile);
  if((notify!=null&&notify.startsWith("payment"))||(event!=null&&event.startsWith("payment.")))return parsePayment(data,profile);
  if(data!=null&&data.containsKey("task_id")&&data.containsKey("target_amount"))return parseDelayTransTransfer(data,profile);
  throw new IllegalArgumentException("无法识别通知类型");
 }
 public static PaymentNotify parsePayment(Map<String,?> data,String profile){return new PaymentNotify(data,config(profile));}
 public static RefundNotify parseRefund(Map<String,?> data,String profile){return new RefundNotify(data,config(profile));}
 public static DelayTransTransferNotify parseDelayTransTransfer(Map<String,?> data,String profile){return new DelayTransTransferNotify(data,config(profile));}
 public static String successResponse(){return SUCCESS_RESPONSE;} public static String failureResponse(){return FAILURE_RESPONSE;}
 private static MerConfig config(String p){return LsPay.getConfig(p);} private static String string(Map<String,?> m,String k){Object v=m==null?null:m.get(k);return v==null?null:String.valueOf(v);}
}
