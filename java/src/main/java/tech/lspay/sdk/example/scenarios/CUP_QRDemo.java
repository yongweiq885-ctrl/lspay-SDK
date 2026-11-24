package tech.lspay.sdk.example.scenarios;

import tech.lspay.sdk.core.LsPay;
import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.core.LsPayResponse;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.example.DemoConstants;
import tech.lspay.sdk.example.Loader;
import tech.lspay.sdk.request.UnifiedOrderRequest;

/** 场景：云闪付扫码 (CUP_QR) */
public class CUP_QRDemo {

    public static void main(String[] args) {
        Loader.init();

        UnifiedOrderRequest req = new UnifiedOrderRequest()
                .setMchOrderNo(DemoConstants.genOrderNo())
                .setWayCode(WayCodeEnum.CUP_QR)
                .setAmount(DemoConstants.DEFAULT_AMOUNT_FEN)
                .setSubject(DemoConstants.DEFAULT_SUBJECT)
                .setBody(DemoConstants.DEFAULT_BODY)
                .setNotifyUrl(LsPay.getConfig().notifyUrl)
                .setChannelExtra("{\"pay_data_type\":\"code_url\"}");

        LsPayResponse resp = LsPayClient.execute(req);
        if (resp.isError()) {
            System.out.println("[CUP_QR] 失败: " + resp.getErrorInfo());
            return;
        }
        System.out.println("[CUP_QR] 云闪付二维码: " + resp.getData());
    }
}
