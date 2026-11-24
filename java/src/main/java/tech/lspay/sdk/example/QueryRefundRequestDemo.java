package tech.lspay.sdk.example;

import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.core.LsPayResponse;
import tech.lspay.sdk.request.QueryRefundRequest;

public class QueryRefundRequestDemo {

    public static void main(String[] args) {
        Loader.init();

        QueryRefundRequest req = new QueryRefundRequest()
                .setMchRefundNo("JR1700000000000");

        LsPayResponse resp = LsPayClient.execute(req);
        if (resp.isError()) {
            System.out.println("退款查询失败: " + resp.getErrorInfo());
            return;
        }
        System.out.println("退款状态: " + resp.getData());
    }
}
