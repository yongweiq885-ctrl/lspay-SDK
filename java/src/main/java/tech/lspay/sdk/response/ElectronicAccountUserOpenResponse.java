package tech.lspay.sdk.response;
public class ElectronicAccountUserOpenResponse extends ElectronicAccountResponse {
 public ElectronicAccountUserOpenResponse(String raw){super(raw);} public String getAccountNo(){return accountValue("account_no");} public String getRespDesc(){return accountValue("resp_desc");}
 public boolean hasAccount(){return getAccountNo()!=null&&!getAccountNo().isEmpty();}
}
