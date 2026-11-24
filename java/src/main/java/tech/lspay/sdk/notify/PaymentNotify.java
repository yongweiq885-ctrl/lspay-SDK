package tech.lspay.sdk.notify;
import tech.lspay.sdk.config.MerConfig; import java.util.Map;
public final class PaymentNotify extends BaseNotify {
 public PaymentNotify(Map<String,?> data, MerConfig config){super(data,config);require("pay_order_id",30);require("mch_order_no",30);require("way_code",30);
  positive("amount");require("currency",3);require("subject",64);require("body",256);if(number("state")==null)throw new IllegalArgumentException("通知 state 非法");
  seconds("add_time",true);seconds("success_time",false);}
 private void seconds(String field, boolean required){String v=value(field);if(v==null||v.isEmpty()){if(required)throw new IllegalArgumentException("通知缺少 "+field);return;}
  if(!v.matches("\\d{10}"))throw new IllegalArgumentException(field+" 必须是 10 位秒级时间戳");}
 public String getMchOrderNo(){return value("mch_order_no");} public Long getAmount(){return number("amount");}
 public String getSubject(){return value("subject");} public String getBody(){return value("body");}
 public boolean isSucceeded(){return Long.valueOf(2).equals(getState())&&(getEventType()==null||"payment.succeeded".equals(getEventType()));}
 public PaymentNotify assertOrder(String no,long amount,String currency){expected("mch_order_no",no);expected("amount",amount);expected("currency",currency.toLowerCase());return this;}
 @Override public String getIdempotencyKey(){return "payment:"+getPayOrderId()+":"+(getEventType()==null?getState():getEventType());}
}
