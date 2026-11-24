package tech.lspay.sdk.example.scenarios;

import com.alibaba.fastjson2.JSON;
import com.alibaba.fastjson2.JSONObject;
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

/** 微信 App / H5 跳转立收通收银台 (WX_LITE_H5)。直连 App 用 WX_APP。 */
public class W_LITE_H5Demo {

    public static void main(String[] args) {
        Loader.init();

        Map<String, Object> extra = new HashMap<>();
        extra.put("pay_data_type", "pay_url");

        UnifiedOrderRequest req = new UnifiedOrderRequest()
                .setMchOrderNo(DemoConstants.genOrderNo())
                .setWayCode(WayCodeEnum.WX_LITE_H5)
                .setAmount(DemoConstants.DEFAULT_AMOUNT_FEN)
                .setSubject(DemoConstants.DEFAULT_SUBJECT)
                .setBody(DemoConstants.DEFAULT_BODY)
                .setClientIp("114.114.114.114")
                .setNotifyUrl(LsPay.getConfig().notifyUrl)
                .setReturnUrl("https://your.domain/pay_result.html")
                .setChannelExtra(extra);

        LsPayResponse resp = LsPayClient.execute(req);
        if (resp.isError()) {
            System.out.println("[WX_LITE_H5] 失败: " + resp.getErrorInfo());
            return;
        }
        UnifiedOrderResponse order = (UnifiedOrderResponse) resp;
        Object payInfo = order.getPayInfo();
        if (payInfo instanceof String) {
            payInfo = JSON.parseObject((String) payInfo);
        }
        if (payInfo instanceof JSONObject) {
            JSONObject info = (JSONObject) payInfo;
            String path = info.getString("path");
            if (path != null) {
                info.put("path", path + "?p=" + order.getPayOrderId() + "&s=app");
            }
        }
        System.out.println("[WX_LITE_H5] App 使用 gh_id 和 path；H5 打开 scheme_code: " + payInfo);
    }
}
