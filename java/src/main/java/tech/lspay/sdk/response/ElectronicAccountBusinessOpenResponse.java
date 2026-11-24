package tech.lspay.sdk.response;
public class ElectronicAccountBusinessOpenResponse extends ElectronicAccountResponse {
 public ElectronicAccountBusinessOpenResponse(String raw){super(raw);} public String getAccountNo(){return accountValue("account_no");} public String getRespDesc(){return accountValue("resp_desc");}
}
