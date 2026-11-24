package tech.lspay.sdk.core;

import com.alibaba.fastjson2.JSON;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.Collection;
import java.util.Map;
import java.util.TreeMap;

/**
 * 立收通工具集
 *   - 签名 / 验签 (sign/verifySign)
 *   - 时间戳 (msecTime)
 *   - 金额换算 (toFen/toYuan)
 */
public final class LsPayTools {

    private LsPayTools() {}

    /** 与 PHP json_encode(JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) 对齐：直出 UTF-8、不转义 / */
    private static String toJsonForSign(Object v) {
        return JSON.toJSONString(v);
    }

    /**
     * 立收通官方签名算法（doc.lspay.tech）
     *   1. 移除 sign 字段
     *   2. 过滤空值（null / 空串）
     *   3. 按字段名 ASCII 升序（TreeMap）
     *   4. 按 k=v&k=v 顺序拼接，Map/Collection 需先 JSON 序列化（不转中文/斜杠）
     *   5. 末尾追加 &key=应用密钥（md5_key）
     *   6. MD5 → 转大写
     *
     * 注意：禁止使用 URLEncoder 对 value 编码，与平台不一致。
     *
     * @param data   待签参数（含或不含 sign 均可，sign 会被忽略）
     * @param secret 商户应用密钥 md5_key（必填）
     * @return 32 位大写 MD5
     */
    public static String sign(Map<String, Object> data, String secret) {
        if (data == null) data = new TreeMap<>();
        TreeMap<String, Object> sorted = new TreeMap<>();
        for (Map.Entry<String, Object> e : data.entrySet()) {
            String k = e.getKey();
            Object v = e.getValue();
            if ("sign".equals(k)) continue;
            if (v == null) continue;
            String s;
            if (v instanceof Map || v instanceof Collection || v.getClass().isArray()) {
                s = toJsonForSign(v);
            } else {
                s = String.valueOf(v);
            }
            if (s.isEmpty()) continue;
            sorted.put(k, s);
        }
        StringBuilder sb = new StringBuilder();
        for (Map.Entry<String, Object> e : sorted.entrySet()) {
            sb.append(e.getKey()).append('=').append(e.getValue()).append('&');
        }
        sb.append("key=").append(secret == null ? "" : secret);
        return md5Upper(sb.toString());
    }

    /** 校验异步通知 / 同步回跳的签名 */
    public static boolean verifySign(Map<String, Object> data, String secret) {
        if (data == null) return false;
        Object signObj = data.get("sign");
        if (signObj == null) return false;
        String expect = sign(data, secret);
        return expect.equalsIgnoreCase(String.valueOf(signObj));
    }

    public static String md5Upper(String src) {
        try {
            MessageDigest md = MessageDigest.getInstance("MD5");
            byte[] bytes = md.digest(src.getBytes(StandardCharsets.UTF_8));
            StringBuilder hex = new StringBuilder(bytes.length * 2);
            for (byte b : bytes) {
                String h = Integer.toHexString(0xff & b);
                if (h.length() == 1) hex.append('0');
                hex.append(h);
            }
            return hex.toString().toUpperCase();
        } catch (Exception e) {
            throw new RuntimeException("MD5 签名失败", e);
        }
    }

    /** 毫秒级时间戳 */
    public static String msecTime() {
        return String.valueOf(System.currentTimeMillis());
    }

    /** 元 → 分（BigDecimal 防精度丢失） */
    public static long toFen(String yuan) {
        if (yuan == null || yuan.isEmpty()) {
            throw new IllegalArgumentException("非法金额: " + yuan);
        }
        return new BigDecimal(yuan).movePointRight(2)
                .setScale(0, RoundingMode.HALF_UP)
                .longValueExact();
    }

    public static long toFen(double yuan) {
        return BigDecimal.valueOf(yuan).movePointRight(2)
                .setScale(0, RoundingMode.HALF_UP)
                .longValueExact();
    }

    /** 分 → 元 */
    public static String toYuan(long fen) {
        return new BigDecimal(fen).movePointLeft(2)
                .setScale(2, RoundingMode.HALF_UP)
                .toPlainString();
    }
}
