package tech.lspay.sdk.core;

import tech.lspay.sdk.request.BaseRequest;
import tech.lspay.sdk.request.RequestFactory;
import tech.lspay.sdk.request.UnifiedOrderRequest;

import java.lang.reflect.Constructor;
import java.util.Map;

/**
 * 立收通客户端
 *
 * 用法：
 *   LsPay.init(...);                     // 注册商户
 *   UnifiedOrderRequest req = new UnifiedOrderRequest()
 *           .setMchOrderNo("...")
 *           .setWayCode(WayCodeEnum.ALI_QR)
 *           .setAmount(1);
 *   LsPayResponse resp = LsPayClient.execute(req);
 */
public final class LsPayClient {

    private LsPayClient() {}

    public static LsPayResponse execute(BaseRequest req) {
        return execute(req, "default");
    }

    public static LsPayResponse execute(BaseRequest req, String profile) {
        if (req == null) throw new IllegalArgumentException("request 不能为 null");
        req.validate();
        Map<String, Object> biz = req.toMap();
        LsPayResponse raw = LsPay.post(req.getFunctionCode(), biz, profile);
        if (req.getResponseClass() == LsPayResponse.class) return raw;
        try {
            Constructor<? extends LsPayResponse> ctor =
                    req.getResponseClass().getConstructor(String.class);
            return ctor.newInstance(raw.getRaw());
        } catch (ReflectiveOperationException e) {
            throw new IllegalStateException("无法创建响应类: " + req.getResponseClass().getName(), e);
        }
    }

    /** 生成 pay_data_type=cashier 的收银台表单，由用户浏览器提交。 */
    public static CashierForm buildCashierForm(UnifiedOrderRequest req) {
        return buildCashierForm(req, "default");
    }

    public static CashierForm buildCashierForm(UnifiedOrderRequest req, String profile) {
        if (req == null) throw new IllegalArgumentException("request 不能为 null");
        req.validate();
        Map<String, Object> biz = req.toMap();
        if (!LsPay.isCashierOrder(biz.get("channel_extra"))) {
            throw new IllegalArgumentException("buildCashierForm 仅用于 channel_extra.pay_data_type=cashier");
        }
        String path = req.getFunctionCode();
        return new CashierForm(LsPay.buildUrl(path), LsPay.buildSignedParams(path, biz, profile));
    }

    public static LsPayResponse execute(String endpoint, Map<String, ?> params) {
        return execute(RequestFactory.fromMap(endpoint, params));
    }

    public static LsPayResponse execute(String endpoint, Map<String, ?> params, String profile) {
        return execute(RequestFactory.fromMap(endpoint, params), profile);
    }
}
