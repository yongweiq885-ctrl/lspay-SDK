package tech.lspay.sdk.notify;
import tech.lspay.sdk.config.MerConfig; import java.util.Map;
public final class DelayTransTransferNotify extends BaseNotify {
 public DelayTransTransferNotify(Map<String,?> data,MerConfig config){super(data,config);require("task_id",64);require("order_no",30);long target=positive("target_amount");
  Long processed=number("processed_amount"),remaining=number("remaining_amount");if(processed==null||remaining==null||processed<0||remaining<0||processed>target||remaining>target)throw new IllegalArgumentException("通知金额关系非法");
  require("status",16);require("account_no",64);require("account_name",128);}
 public String getTaskId(){return value("task_id");} public String getOrderNo(){return value("order_no");} public Long getTargetAmount(){return number("target_amount");}
 public Long getProcessedAmount(){return number("processed_amount");} public Long getRemainingAmount(){return number("remaining_amount");} public String getTaskStatus(){return value("status");}
 public boolean isSucceeded(){return "COMPLETED".equals(getTaskStatus());} public boolean isPartial(){return "PARTIAL".equals(getTaskStatus());}
 public DelayTransTransferNotify assertTask(String no,long amount){expected("order_no",no);expected("target_amount",amount);return this;}
 @Override public String getIdempotencyKey(){return "delaytrans-transfer:"+getTaskId()+":"+getTaskStatus()+":"+getProcessedAmount();}
}
