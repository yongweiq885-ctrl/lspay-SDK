package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.EndpointEnum; import tech.lspay.sdk.response.QuickPaySmsCheckResponse;
public class QuickPaySmsCheckRequest extends QuickPayConfirmRequest {
 @Override public String getFunctionCode(){return EndpointEnum.QUICK_PAY_SMS_CHECK;}
 @Override public Class<QuickPaySmsCheckResponse> getResponseClass(){return QuickPaySmsCheckResponse.class;}
}
