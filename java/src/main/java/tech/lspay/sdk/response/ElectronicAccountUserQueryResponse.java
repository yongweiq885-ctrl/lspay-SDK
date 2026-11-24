package tech.lspay.sdk.response;
public class ElectronicAccountUserQueryResponse extends ElectronicAccountResponse {
 public ElectronicAccountUserQueryResponse(String raw){super(raw);} public String getAccountNo(){return accountValue("account_no");}
 public String getAccountName(){return accountValue("account_name");} public String getAccountType(){return accountValue("account_type");}
 public String getRelationType(){return accountValue("relation_type");} public String getRelationTypeName(){return accountValue("relation_type_name");}
 public String getDivisionProfit(){return accountValue("division_profit");}
 public String getChannelStatus(){return accountValue("channel_status");}
 public String getRespDesc(){return accountValue("resp_desc");}
 public String getChannelExtra(){
  Object value=getData().containsKey("channel_extra")?getData().get("channel_extra"):getMap().get("channel_extra");
  if(value==null)return null;
  return value instanceof String?(String)value:com.alibaba.fastjson2.JSON.toJSONString(value);
 }
 /** 查询资料中的 channel_status 是否为 1。开户成功看开户接口的 trans_status。 */
 public boolean isOnboarded(){return isSuccessful()&&"1".equals(getChannelStatus());}
}
