package tech.lspay.sdk.example;

import com.alibaba.fastjson2.JSON;
import com.alibaba.fastjson2.JSONObject;
import tech.lspay.sdk.core.LsPay;
import tech.lspay.sdk.core.LsPayTools;

import java.util.Map;

/**
 * 异步通知 (notify_url) 验签 Demo
 *
 * 真实场景：
 *   - 服务端接收 POST，读取 raw body
 *   - 反序列化成 Map
 *   - 调 LsPayTools.verifySign 校验
 *   - 验签通过 → 处理业务 → 返回 "SUCCESS"
 *   - 验签失败 → 返回 "FAIL"
 */
public class NotifyVerifyDemo {

    public static void main(String[] args) {
        Loader.init();

        // 模拟一个回调 body
        String body = "{\"mch_no\":\"M001\",\"mch_order_no\":\"JT123\",\"amount\":1,\"status\":\"SUCCESS\",\"sign\":\"PUT_REAL_SIGN_HERE\"}";

        JSONObject json = JSON.parseObject(body);
        Map<String, Object> map = json;

        String md5Key = LsPay.getConfig("default").md5Key;
        boolean ok = LsPayTools.verifySign(map, md5Key);

        if (ok) {
            System.out.println("验签通过，处理业务...");
            System.out.println("Reply: SUCCESS");
        } else {
            System.out.println("验签失败，拒绝处理");
            System.out.println("Reply: FAIL");
        }
    }
}
