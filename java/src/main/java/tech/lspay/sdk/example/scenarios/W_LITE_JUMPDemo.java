package tech.lspay.sdk.example.scenarios;

import com.alibaba.fastjson2.JSON;
import tech.lspay.sdk.core.LsPay;
import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.core.LsPayResponse;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.example.DemoConstants;
import tech.lspay.sdk.example.Loader;
import tech.lspay.sdk.request.UnifiedOrderRequest;
import tech.lspay.sdk.response.UnifiedOrderResponse;

import java.util.HashMap;
import java.util.Map;

/** 自有微信小程序跳立收通收银台 (WX_LITE_JUMP)。 */
public class W_LITE_JUMPDemo {

    public static void main(String[] args) {
        Loader.init();

        Map<String, Object> extra = new HashMap<>();
        extra.put("openid", "oUpF8uMuAJO_M2pxb1Q9zNjWeS6o");

        UnifiedOrderRequest req = new UnifiedOrderRequest()
                .setMchOrderNo(DemoConstants.genOrderNo())
                .setWayCode(WayCodeEnum.WX_LITE_JUMP)
                .setAmount(DemoConstants.DEFAULT_AMOUNT_FEN)
                .setSubject(DemoConstants.DEFAULT_SUBJECT)
                .setBody(DemoConstants.DEFAULT_BODY)
                .setNotifyUrl(LsPay.getConfig().notifyUrl)
                .setChannelExtra(extra);

        LsPayResponse resp = LsPayClient.execute(req);
        if (resp.isError()) {
            System.out.println("[WX_LITE_JUMP] 失败: " + resp.getErrorInfo());
            return;
        }
        UnifiedOrderResponse order = (UnifiedOrderResponse) resp;
        Object payInfo = order.getPayInfo();
        if (payInfo instanceof String) {
            payInfo = JSON.parseObject((String) payInfo);
        }
        System.out.println("[WX_LITE_JUMP] 使用 app_id 和 path 跳转: " + payInfo);
    }
}
