package tech.lspay.sdk.enums;

import java.util.LinkedHashMap;
import java.util.Map;
import java.util.Arrays;

/**
 * 立收通统一网关 way_code。
 *
 * way_code 不仅包含支付方式，也包含用户入驻、余额、结算和提现等业务能力。
 */
public final class WayCodeEnum {

    private WayCodeEnum() {}

    // ============ 支付宝 ============
    public static final String ALI_BAR     = "ALI_BAR";
    public static final String ALI_PUB     = "ALI_PUB";
    public static final String ALI_APP     = "ALI_APP";
    public static final String ALI_H5      = "ALI_H5";
    public static final String ALI_LITE_H5 = "ALI_LITE_H5";
    public static final String ALI_PC      = "ALI_PC";
    public static final String ALI_QR      = "ALI_QR";
    public static final String ALI_LITE    = "ALI_LITE";

    // ============ 微信 ============
    public static final String WX_BAR       = "WX_BAR";
    public static final String WX_PUB       = "WX_PUB";
    public static final String WX_LITE      = "WX_LITE";
    public static final String WX_APP       = "WX_APP";
    public static final String WX_LITE_H5   = "WX_LITE_H5";
    public static final String WX_LITE_JUMP = "WX_LITE_JUMP";
    public static final String WX_NATIVE    = "WX_NATIVE";

    // ============ 云闪付 / 银联 ============
    public static final String YSF_BAR   = "YSF_BAR";
    public static final String YSF_JSAPI = "YSF_JSAPI";
    public static final String CUP_QR    = "CUP_QR";

    // ============ 快捷支付 ============
    public static final String BANK_FAST      = "BANK_FAST";
    public static final String BANK_FAST_JUMP = "BANK_FAST_JUMP";

    // ============ 平台业务 ============
    public static final String APPLY            = "APPLY";
    public static final String BALANCE_PAY      = "BALANCE_PAY";
    public static final String BALANCE_QUERY    = "BALANCE_QUERY";
    public static final String SETTLE_QUERY     = "SETTLE_QUERY";
    public static final String BALANCE_WITHDRAW = "BALANCE_WITHDRAW";
    public static final String WITHDRAW_SINGLE_QUERY = "WITHDRAW_SINGLE_QUERY";
    public static final String WITHDRAW_QUERY = "WITHDRAW_QUERY";

    /** 获取所有支持的 way_code 与中文描述 */
    public static Map<String, String> all() {
        Map<String, String> m = new LinkedHashMap<>();
        m.put(ALI_BAR,     "支付宝条码");
        m.put(ALI_PUB,     "支付宝 JSAPI（间连，线上扫码推荐）");
        m.put(ALI_APP,     "支付宝 APP（直连）");
        m.put(ALI_H5,      "支付宝 H5（直连）");
        m.put(ALI_LITE_H5, "支付宝外或 App 唤起收银台");
        m.put(ALI_PC,      "支付宝电脑网站（直连）");
        m.put(ALI_QR,      "支付宝扫码（间连，可在支付宝外拉起收银台）");
        m.put(ALI_LITE,    "支付宝小程序（直连）");
        m.put(WX_BAR,       "微信条码");
        m.put(WX_PUB,       "微信 JSAPI（间连，扫码或公众号）");
        m.put(WX_LITE,      "微信小程序");
        m.put(WX_APP,       "微信 APP（直连）");
        m.put(WX_LITE_H5,   "微信外或 App 唤起收银台（建议安卓）");
        m.put(WX_LITE_JUMP, "小程序跳立收通收银台");
        m.put(WX_NATIVE,    "微信扫码（直连）");
        m.put(YSF_BAR,   "云闪付条码");
        m.put(YSF_JSAPI, "云闪付线上（小程序或扫码）");
        m.put(CUP_QR,    "云闪付扫码");
        m.put(BANK_FAST,      "快捷支付");
        m.put(BANK_FAST_JUMP, "快捷支付收银台");
        m.put(APPLY,            "用户入驻");
        m.put(BALANCE_PAY,      "余额支付");
        m.put(BALANCE_QUERY,    "余额查询");
        m.put(SETTLE_QUERY,     "结算查询");
        m.put(BALANCE_WITHDRAW, "余额提现");
        m.put(WITHDRAW_SINGLE_QUERY, "单笔提现查询");
        m.put(WITHDRAW_QUERY, "批量提现查询");
        return m;
    }

    /** 判断 way_code 是否在平台清单中。 */
    public static boolean isValid(String wayCode) {
        return wayCode != null && all().containsKey(wayCode);
    }

    /** 统一下单可选的 20 种支付方式，不含余额/开户/提现业务码。 */
    public static Map<String, String> paymentMethods() {
        Map<String, String> result = new LinkedHashMap<>();
        Map<String, String> all = all();
        for (String code : Arrays.asList(
                ALI_BAR, ALI_PUB, ALI_APP, ALI_H5, ALI_LITE_H5, ALI_PC, ALI_QR, ALI_LITE,
                WX_BAR, WX_PUB, WX_LITE, WX_APP, WX_LITE_H5, WX_LITE_JUMP, WX_NATIVE,
                YSF_BAR, YSF_JSAPI, CUP_QR, BANK_FAST, BANK_FAST_JUMP)) {
            result.put(code, all.get(code));
        }
        return result;
    }
}
