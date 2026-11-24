package tech.lspay.sdk.example;

import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.core.LsPayResponse;
import tech.lspay.sdk.request.RefundRequest;

public class RefundRequestDemo {

    public static void main(String[] args) {
        Loader.init();

        RefundRequest req = new RefundRequest()
                .setMchRefundNo(DemoConstants.genRefundNo())
                .setMchOrderNo("JT1700000000000")
                .setRefundAmount(1L)
                .setPayAmount(1L)
                .setRefundReason("用户申请退款");

        LsPayResponse resp = LsPayClient.execute(req);
        if (resp.isError()) {
            System.out.println("退款失败: " + resp.getErrorInfo());
            return;
        }
        System.out.println("退款受理: " + resp.getData());
    }
}
