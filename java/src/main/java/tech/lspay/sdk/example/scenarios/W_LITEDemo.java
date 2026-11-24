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

/** 微信小程序原生支付 (WX_LITE)。channel_extra 为 {"openid":"..."}，pay_info 解析后调起支付。 */
public class W_LITEDemo {

    public static void main(String[] args) {
        Loader.init();

        Map<String, Object> extra = new HashMap<>();
        extra.put("openid", "oUpF8uMuAJO_M2pxb1Q9zNjWeS6o");

        UnifiedOrderRequest req = new UnifiedOrderRequest()
                .setMchOrderNo(DemoConstants.genOrderNo())
                .setWayCode(WayCodeEnum.WX_LITE)
                .setAmount(DemoConstants.DEFAULT_AMOUNT_FEN)
                .setSubject(DemoConstants.DEFAULT_SUBJECT)
                .setBody(DemoConstants.DEFAULT_BODY)
                .setNotifyUrl(LsPay.getConfig().notifyUrl)
                .setChannelExtra(extra);

        LsPayResponse resp = LsPayClient.execute(req);
        if (resp.isError()) {
            System.out.println("[WX_LITE] 失败: " + resp.getErrorInfo());
            return;
        }
        UnifiedOrderResponse order = (UnifiedOrderResponse) resp;
        Object payInfo = order.getPayInfo();
        if (payInfo instanceof String) {
            payInfo = JSON.parseObject((String) payInfo);
        }
        System.out.println("[WX_LITE] 调起参数: " + payInfo);
    }
}
