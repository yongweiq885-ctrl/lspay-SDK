package tech.lspay.sdk.request;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.response.QuickPayApplyResponse;
public class QuickPayApplyRequest extends UnifiedOrderRequest {
 public QuickPayApplyRequest(){wayCode=WayCodeEnum.BANK_FAST;}
 @Override public Class<QuickPayApplyResponse> getResponseClass(){return QuickPayApplyResponse.class;}
 @Override public QuickPayApplyRequest setWayCode(String v){fixed("way_code",v,WayCodeEnum.BANK_FAST);wayCode=v;return this;}
 @Override public void validate(){super.validate();fixed("way_code",wayCode,WayCodeEnum.BANK_FAST);}
}
