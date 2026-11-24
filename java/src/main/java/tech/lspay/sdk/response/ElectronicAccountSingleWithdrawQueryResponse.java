package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse;
public class ElectronicAccountSingleWithdrawQueryResponse extends LsPayResponse {
 public ElectronicAccountSingleWithdrawQueryResponse(String raw){super(raw);} public String getWithdrawDate(){return stringValue("withdraw_date");}
 public String getOrderId(){return stringValue("order_id");} public String getCashAmount(){return stringValue("cash_amt");} public String getFeeAmount(){return stringValue("fee_amt");}
 public String getTransStatus(){return stringValue("trans_status");} public String getTransDescription(){return stringValue("trans_desc");}
 public String getChannelStatus(){return stringValue("status");} public String getChannelMessage(){return stringValue("err_msg");}
 public boolean isPending(){return !isError()&&"pending".equals(getTransStatus());}
 public boolean isFailed(){return !isError()&&"failed".equals(getTransStatus());}
 public boolean isSucceeded(){return !isError()&&"succeeded".equals(getTransStatus());}
 public boolean isChannelSuccessful(){return "success".equalsIgnoreCase(getChannelStatus())||"succeess".equalsIgnoreCase(getChannelStatus());}
}
