package tech.lspay.sdk.example.scenarios;

import tech.lspay.sdk.core.LsPay;
import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.core.LsPayResponse;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.example.DemoConstants;
import tech.lspay.sdk.example.Loader;
import tech.lspay.sdk.request.UnifiedOrderRequest;

/**
 * 场景：微信 JSAPI (WX_PUB)
 *
 * 适用：公众号或微信内 H5 唤起支付；必须通过 channel_extra 传 openid。
 * 微信扫码请使用 WX_NATIVE。
 */
public class W_PUBDemo {

    public static void main(String[] args) {
        Loader.init();

        UnifiedOrderRequest req = new UnifiedOrderRequest()
                .setMchOrderNo(DemoConstants.genOrderNo())
                .setWayCode(WayCodeEnum.WX_PUB)
                .setAmount(DemoConstants.DEFAULT_AMOUNT_FEN)
                .setSubject(DemoConstants.DEFAULT_SUBJECT)
                .setBody(DemoConstants.DEFAULT_BODY)
                .setNotifyUrl(LsPay.getConfig().notifyUrl);

        // 必填：发起真实请求前替换为公众号/微信内授权获得的 openid。
        java.util.Map<String, Object> ce = new java.util.HashMap<>();
        ce.put("openid", "o-XXXXXXXXXXXXXXXXXXXXXXXX");
        req.setChannelExtra(ce);

        LsPayResponse resp = LsPayClient.execute(req);
        if (resp.isError()) {
            System.out.println("[WX_PUB] 失败: " + resp.getErrorInfo());
            return;
        }
        System.out.println("[WX_PUB] 微信支付信息: " + resp.getData());
    }
}
