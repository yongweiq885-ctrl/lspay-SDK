package tech.lspay.sdk.example;

import tech.lspay.sdk.core.LsPay;
import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.core.LsPayResponse;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.request.UnifiedOrderRequest;

/**
 * 统一下单 - 支付宝当面付 (ALI_QR) 示例
 *
 * 运行:
 *   mvn -q compile exec:java -Dexec.mainClass=tech.lspay.sdk.example.UnifiedOrderRequestDemo
 */
public class UnifiedOrderRequestDemo {

    public static void main(String[] args) {
        Loader.init();

        UnifiedOrderRequest req = new UnifiedOrderRequest()
                .setMchOrderNo(DemoConstants.genOrderNo())
                .setWayCode(WayCodeEnum.ALI_QR)
                .setAmount(DemoConstants.DEFAULT_AMOUNT_FEN)
                .setSubject(DemoConstants.DEFAULT_SUBJECT)
                .setBody(DemoConstants.DEFAULT_BODY)
                .setNotifyUrl(LsPay.getConfig("default").notifyUrl)
                .setClientIp("127.0.0.1")
                .setChannelExtra("{\"pay_data_type\":\"pay_url\"}");

        LsPayResponse resp = LsPayClient.execute(req);
        if (resp.isError()) {
            System.out.println("下单失败: " + resp.getErrorInfo());
            return;
        }
        System.out.println("下单成功: " + resp.getData());
    }
}
