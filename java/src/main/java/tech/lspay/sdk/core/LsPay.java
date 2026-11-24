package tech.lspay.sdk.core;

import com.alibaba.fastjson2.JSON;
import com.alibaba.fastjson2.JSONObject;
import tech.lspay.sdk.config.MerConfig;
import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.enums.PayDataTypeEnum;
import tech.lspay.sdk.exceptions.LsPayConfigException;
import tech.lspay.sdk.internal.HttpUtil;

import java.io.UnsupportedEncodingException;
import java.net.URI;
import java.net.URISyntaxException;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.LinkedHashMap;
import java.util.Arrays;
import java.util.HashSet;
import java.util.Map;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;
import java.util.function.Consumer;

/**
 * 立收通 SDK 总控
 *
 *   - 多商户配置注册 / 取出
 *   - 全局网关地址、超时、insecure、日志
 *   - 真正的 HTTP 发起
 */
public final class LsPay {

    private LsPay() {}

    // ============ 全局配置 ============
    public static String GATEWAY    = "https://api.lspay.tech/";
    /** API 接口版本号（写入每次请求的 version 字段） */
    public static String API_VERSION = "1.0";
    /** 签名算法（写入每次请求的 sign_type 字段） */
    public static String SIGN_TYPE   = "MD5";

    public static int    CONNECT_MS = 10_000;
    public static int    READ_MS    = 15_000;
    public static boolean DEBUG     = false;
    public static boolean PROD_MODE = true;
    public static boolean INSECURE  = false;
    public static Consumer<String> LOGGER = null;

    // ============ 多商户表 ============
    private static final Map<String, MerConfig> CONFIGS = new ConcurrentHashMap<>();
    private static final Set<String> RESERVED = new HashSet<>(Arrays.asList(
            "mch_no", "app_id", "req_time", "version", "sign_type", "sign"));
    private static final Set<String> SENSITIVE = new HashSet<>(Arrays.asList(
            "sign", "md5_key", "auth_code", "openid", "buyer_id", "trans_in", "trans_out",
            "account_no", "account_name", "account_alias", "mch_user_id", "card_id",
            "card_name", "card_no", "cert_id", "cert_no", "card_mp", "verify_code",
            "channel_user", "channel_card_id", "name", "mobile_no", "email", "reg_name",
            "license_code", "legal_name", "legal_cert_no", "contact_name", "contact_mobile",
            "mp", "pay_info"));

    // ============ 注册接口 ============
    public static void init(MerConfig cfg) {
        init("default", cfg);
    }

    public static void init(String profile, MerConfig cfg) {
        if (cfg == null)                     throw new LsPayConfigException("MerConfig 不能为空");
        cfg.mchNo     = trim(cfg.mchNo);
        cfg.appId     = trim(cfg.appId);
        cfg.notifyUrl = trim(cfg.notifyUrl);
        cfg.returnUrl = trim(cfg.returnUrl);
        if (isBlank(cfg.mchNo))              throw new LsPayConfigException("mch_no 必填");
        if (isBlank(cfg.appId))              throw new LsPayConfigException("app_id 必填");
        if (isBlank(cfg.md5Key))             throw new LsPayConfigException("md5_key 必填");
        if (cfg.mchNo.length() > 30)         throw new LsPayConfigException("mch_no 长度不能超过 30");
        if (cfg.appId.length() > 24)         throw new LsPayConfigException("app_id 长度不能超过 24");
        checkHttpUrl("notify_url", cfg.notifyUrl);
        checkHttpUrl("return_url", cfg.returnUrl);
        CONFIGS.put(profile, cfg);
    }

    private static void checkHttpUrl(String field, String url) {
        if (url.isEmpty()) return;
        boolean ok = url.length() <= 128;
        if (ok) {
            try {
                URI uri = new URI(url);
                String scheme = uri.getScheme() == null ? "" : uri.getScheme().toLowerCase();
                ok = ("http".equals(scheme) || "https".equals(scheme)) && !isBlank(uri.getHost());
            } catch (URISyntaxException e) {
                ok = false;
            }
        }
        if (!ok) throw new LsPayConfigException(field + " 必须是长度不超过 128 的 HTTP(S) URL");
    }

    private static String trim(String s) { return s == null ? "" : s.trim(); }

    public static MerConfig getConfig() { return getConfig("default"); }

    public static MerConfig getConfig(String profile) {
        MerConfig c = CONFIGS.get(profile);
        if (c == null) throw new LsPayConfigException("未注册商户配置: " + profile);
        return c;
    }

    public static boolean hasConfig(String profile) {
        return CONFIGS.containsKey(profile);
    }

    public static void clear() { CONFIGS.clear(); }

    // ============ HTTP ============
    /**
     * 已包装签名 / 编码 / 解析的 POST 调用
     *
     * 公共字段: mch_no / app_id / req_time / version / sign_type / sign
     * 传输格式: application/x-www-form-urlencoded;charset=utf-8
     *
     * @param path     接口路径，例如 gateway/unifiedorder
     * @param bizParams 业务参数（不含 mch_no / app_id / req_time / version / sign_type / sign）
     * @param profile  商户 profile
     */
    public static LsPayResponse post(String path, Map<String, Object> bizParams, String profile) {
        if (EndpointEnum.UNIFIED_ORDER.equals(path) && bizParams != null && isCashierOrder(bizParams.get("channel_extra"))) {
            throw new IllegalArgumentException("pay_data_type=cashier 时网关返回收银台页面，不能由服务端调用接口；"
                    + "请使用 LsPayClient.buildCashierForm() 生成表单，由用户浏览器提交");
        }
        Map<String, Object> all = buildSignedParams(path, bizParams, profile);

        String url  = buildUrl(path);
        String body = buildFormBody(all);
        log(">>> POST " + url + " body=" + buildFormBody(sanitizeForLog(all)));

        String resp = HttpUtil.postForm(url, body, CONNECT_MS, READ_MS, INSECURE);
        log("<<< " + sanitizeResponse(resp));
        return new LsPayResponse(resp);
    }

    /** channel_extra.pay_data_type 是否为 cashier。 */
    @SuppressWarnings("unchecked")
    public static boolean isCashierOrder(Object channelExtra) {
        if (channelExtra == null) return false;
        try {
            JSONObject extra = channelExtra instanceof Map
                    ? new JSONObject((Map<String, Object>) channelExtra)
                    : JSON.parseObject(String.valueOf(channelExtra));
            return extra != null && PayDataTypeEnum.CASHIER.equals(extra.getString("pay_data_type"));
        } catch (RuntimeException e) {
            return false;
        }
    }

    /** 注入公共字段并签名，返回可直接提交的参数。 */
    public static Map<String, Object> buildSignedParams(String path, Map<String, Object> bizParams, String profile) {
        MerConfig cfg = getConfig(profile);

        Map<String, Object> all = new LinkedHashMap<>();
        all.put("mch_no",    cfg.mchNo);
        all.put("app_id",    cfg.appId);
        all.put("req_time",  LsPayTools.msecTime());
        all.put("version",   API_VERSION);
        all.put("sign_type", SIGN_TYPE);
        if (bizParams != null) {
            for (Map.Entry<String, Object> e : bizParams.entrySet()) {
                if (RESERVED.contains(e.getKey())) {
                    throw new IllegalArgumentException(e.getKey() + " 是 SDK 管理的公共参数");
                }
                Object v = e.getValue();
                if (v == null) continue;
                if (v instanceof String && ((String) v).isEmpty()) continue;
                all.put(e.getKey(), v);
            }
        }
        if (EndpointEnum.UNIFIED_ORDER.equals(path)) {
            if (!all.containsKey("notify_url") && !isBlank(cfg.notifyUrl)) all.put("notify_url", cfg.notifyUrl);
            if (!all.containsKey("return_url") && !isBlank(cfg.returnUrl)) all.put("return_url", cfg.returnUrl);
        }

        all.put("sign", LsPayTools.sign(all, cfg.md5Key));
        return all;
    }

    /**
     * 构造 application/x-www-form-urlencoded 请求体。
     * 注意：value 必须 URL 编码（PHP http_build_query 等价），但 sign 已是大写 MD5，编码后不变。
     */
    private static String buildFormBody(Map<String, Object> data) {
        StringBuilder sb = new StringBuilder();
        boolean first = true;
        for (Map.Entry<String, Object> e : data.entrySet()) {
            Object v = e.getValue();
            if (v == null) continue;
            String s = String.valueOf(v);
            if (!first) sb.append('&');
            sb.append(urlEnc(e.getKey())).append('=').append(urlEnc(s));
            first = false;
        }
        return sb.toString();
    }

    private static String urlEnc(String s) {
        try {
            return URLEncoder.encode(s, StandardCharsets.UTF_8.name());
        } catch (UnsupportedEncodingException e) {
            return s;
        }
    }

    public static String buildUrl(String path) {
        String base = GATEWAY.endsWith("/") ? GATEWAY : GATEWAY + "/";
        String tail = path.startsWith("/") ? path.substring(1) : path;
        return base + tail;
    }

    private static void log(String line) {
        if (!DEBUG && LOGGER == null) return;
        if (LOGGER != null) LOGGER.accept(line);
        if (DEBUG)          System.out.println("[LsPay] " + line);
    }

    @SuppressWarnings("unchecked")
    public static Map<String, Object> sanitizeForLog(Map<String, ?> source) {
        Map<String, Object> result = new LinkedHashMap<>();
        if (source == null) return result;
        for (Map.Entry<String, ?> entry : source.entrySet()) {
            Object value = entry.getValue();
            if (SENSITIVE.contains(entry.getKey())) {
                result.put(entry.getKey(), mask(value));
            } else if (value instanceof Map) {
                result.put(entry.getKey(), sanitizeForLog((Map<String, ?>) value));
            } else if ("channel_extra".equals(entry.getKey()) && value instanceof String) {
                try {
                    Map<String, Object> extra = com.alibaba.fastjson2.JSON.parseObject(
                            (String) value, Map.class);
                    result.put(entry.getKey(), com.alibaba.fastjson2.JSON.toJSONString(sanitizeForLog(extra)));
                } catch (RuntimeException ignored) {
                    result.put(entry.getKey(), "[invalid-json]");
                }
            } else result.put(entry.getKey(), value);
        }
        return result;
    }

    private static String sanitizeResponse(String raw) {
        try {
            Map<String, Object> map = com.alibaba.fastjson2.JSON.parseObject(raw, Map.class);
            return com.alibaba.fastjson2.JSON.toJSONString(sanitizeForLog(map));
        } catch (RuntimeException e) {
            return "[response omitted; length=" + (raw == null ? 0 : raw.length()) + "]";
        }
    }

    private static String mask(Object value) {
        String s = String.valueOf(value);
        return s.length() <= 4 ? "****" : s.substring(0, 2) + "****" + s.substring(s.length() - 2);
    }

    private static boolean isBlank(String s) { return s == null || s.trim().isEmpty(); }
}
