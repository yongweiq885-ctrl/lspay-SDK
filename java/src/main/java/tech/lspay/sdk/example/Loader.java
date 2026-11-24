package tech.lspay.sdk.example;

import tech.lspay.sdk.config.MerConfig;
import tech.lspay.sdk.core.LsPay;

import java.util.ArrayList;
import java.util.List;

/**
 * Demo 通用初始化
 *
 *   - 把 SDK 调到 DEBUG=true
 *   - 从环境变量注册 default 商户；设置了 LSPAY_B_* 时再注册 merch_b 商户
 *   - 沙箱可改 GATEWAY
 *
 * 真实项目里：
 *   - 商户号 / appId / md5Key 应来自配置中心、环境变量、安全网关
 *   - 不要把密钥写死在代码
 */
public final class Loader {

    private Loader() {}

    private static boolean inited = false;

    public static synchronized void init() {
        if (inited) return;

        LsPay.DEBUG     = true;
        LsPay.PROD_MODE = true;
        LsPay.GATEWAY   = "https://api.lspay.tech/";

        LsPay.init("default", loadConfig("LSPAY_"));
        if (sysEnv("LSPAY_B_MCH_NO") != null || sysEnv("LSPAY_B_APP_ID") != null
                || sysEnv("LSPAY_B_MD5_KEY") != null) {
            LsPay.init("merch_b", loadConfig("LSPAY_B_"));
        }
        inited = true;
    }

    private static MerConfig loadConfig(String prefix) {
        List<String> missing = new ArrayList<>();
        MerConfig cfg = new MerConfig();
        cfg.mchNo     = required(prefix + "MCH_NO", missing);
        cfg.appId     = required(prefix + "APP_ID", missing);
        cfg.md5Key    = required(prefix + "MD5_KEY", missing);
        cfg.notifyUrl = sysEnv(prefix + "NOTIFY_URL");
        cfg.returnUrl = sysEnv(prefix + "RETURN_URL");
        if (!missing.isEmpty()) {
            throw new IllegalStateException("未配置商户信息，请先配置环境变量 " + String.join("、", missing));
        }
        return cfg;
    }

    private static String required(String key, List<String> missing) {
        String v = sysEnv(key);
        if (v == null) missing.add(key);
        return v;
    }

    private static String sysEnv(String key) {
        String v = System.getenv(key);
        return (v == null || v.trim().isEmpty()) ? null : v.trim();
    }
}
