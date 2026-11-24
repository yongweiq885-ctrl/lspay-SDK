package tech.lspay.sdk.request;

import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.response.DelayTransUnconfirmQueryResponse;

public class DelayTransUnconfirmQueryRequest extends BaseRequest {
    @Override public String getFunctionCode() { return EndpointEnum.DELAY_TRANS_UNCONFIRM_QUERY; }
    @Override public Class<DelayTransUnconfirmQueryResponse> getResponseClass() { return DelayTransUnconfirmQueryResponse.class; }
}
