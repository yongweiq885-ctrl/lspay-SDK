package tech.lspay.sdk.response;
public class ElectronicAccountBusinessChangeResponse extends ElectronicAccountResponse {
 public ElectronicAccountBusinessChangeResponse(String raw){super(raw);} public String getAccountNo(){return accountValue("account_no");} public String getRespDesc(){return accountValue("resp_desc");}
}
