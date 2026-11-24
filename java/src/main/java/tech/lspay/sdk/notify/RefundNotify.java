package tech.lspay.sdk.notify;
import tech.lspay.sdk.config.MerConfig; import java.util.Map;
public final class RefundNotify extends BaseNotify {
 public RefundNotify(Map<String,?> data, MerConfig config){super(data,config);if(!"refund".equals(require("notify_type",64)))throw new IllegalArgumentException("notify_type 必须是 refund");
  require("event_type",64);require("pay_order_id",30);require("mch_refund_no",30);require("way_code",30);long pay=positive("pay_amount");long refund=positive("refund_amount");
  if(refund>pay)throw new IllegalArgumentException("refund_amount 不能大于 pay_amount");require("currency",3);if(number("state")==null)throw new IllegalArgumentException("state 非法");}
 public String getMchRefundNo(){return value("mch_refund_no");} public Long getPayAmount(){return number("pay_amount");} public Long getRefundAmount(){return number("refund_amount");}
 public boolean isSucceeded(){return Long.valueOf(2).equals(getState())&&"refund.succeeded".equals(getEventType());}
 public RefundNotify assertRefund(String no,long amount,String currency){expected("mch_refund_no",no);expected("refund_amount",amount);expected("currency",currency.toLowerCase());return this;}
 @Override public String getIdempotencyKey(){return "refund:"+getMchRefundNo()+":"+getEventType();}
}
