package tech.lspay.sdk.example.scenarios;

import tech.lspay.sdk.core.LsPay;
import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.core.LsPayResponse;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.example.DemoConstants;
import tech.lspay.sdk.example.Loader;
import tech.lspay.sdk.request.UnifiedOrderRequest;

/** 场景：支付宝 H5/APP 收银台 (ALI_LITE_H5) */
public class A_LITE_H5Demo {

    public static void main(String[] args) {
        Loader.init();

        UnifiedOrderRequest req = new UnifiedOrderRequest()
                .setMchOrderNo(DemoConstants.genOrderNo())
                .setWayCode(WayCodeEnum.ALI_LITE_H5)
                .setAmount(DemoConstants.DEFAULT_AMOUNT_FEN)
                .setSubject(DemoConstants.DEFAULT_SUBJECT)
                .setBody(DemoConstants.DEFAULT_BODY)
                .setClientIp("114.114.114.114")
                .setNotifyUrl(LsPay.getConfig().notifyUrl)
                .setReturnUrl("https://your.domain/pay_result.html")
                .setChannelExtra("{\"pay_data_type\":\"pay_url\"}");

        LsPayResponse resp = LsPayClient.execute(req);
        if (resp.isError()) {
            System.out.println("[ALI_LITE_H5] 失败: " + resp.getErrorInfo());
            return;
        }
        System.out.println("[ALI_LITE_H5] App 打开 jump_url。app_schema 要带 :// : " + resp.getData());
    }
}
