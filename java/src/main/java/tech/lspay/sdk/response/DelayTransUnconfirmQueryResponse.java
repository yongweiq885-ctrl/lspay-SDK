package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse;
public class DelayTransUnconfirmQueryResponse extends LsPayResponse {
    public DelayTransUnconfirmQueryResponse(String raw){super(raw);}
    public Long getUnconfirmAmount(){return longValue("unconfirm_amount");}
}
