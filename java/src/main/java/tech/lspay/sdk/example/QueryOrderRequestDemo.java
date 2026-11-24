package tech.lspay.sdk.example;

import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.core.LsPayResponse;
import tech.lspay.sdk.request.QueryOrderRequest;

public class QueryOrderRequestDemo {

    public static void main(String[] args) {
        Loader.init();

        QueryOrderRequest req = new QueryOrderRequest()
                .setMchOrderNo("JT1700000000000");

        LsPayResponse resp = LsPayClient.execute(req);
        if (resp.isError()) {
            System.out.println("查询失败: " + resp.getErrorInfo());
            return;
        }
        System.out.println("订单状态: " + resp.getData());
    }
}
