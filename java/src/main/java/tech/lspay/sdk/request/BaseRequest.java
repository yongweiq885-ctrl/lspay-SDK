package tech.lspay.sdk.request;

import com.alibaba.fastjson2.JSON;
import com.alibaba.fastjson2.JSONObject;
import tech.lspay.sdk.core.LsPayResponse;

import java.lang.reflect.Field;
import java.net.URI;
import java.util.Arrays;
import java.util.Collection;
import java.util.HashSet;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.Set;
import java.util.TreeMap;

/**
 * 所有接口请求对象的抽象基类
 *
 *   - getFunctionCode()  返回接口路径（EndpointEnum 常量）
 *   - extendInfos       业务方自定义透传字段（key=>value）
 *   - toMap()            通过反射把 public 字段拼成 Map
 */
public abstract class BaseRequest {

    private static final Set<String> RESERVED = new HashSet<>(Arrays.asList(
            "mch_no", "app_id", "req_time", "version", "sign_type", "sign"));

    /** 业务方自定义透传字段；放进顶层 body */
    public Map<String, Object> extendInfos = new LinkedHashMap<>();

    /** 子类实现：返回接口路径（EndpointEnum 常量），如 "gateway/unifiedorder" */
    public abstract String getFunctionCode();

    /** 发起请求前执行；子类覆盖以实现接口契约。 */
    public void validate() {}

    /** 当前请求的强类型响应类。 */
    public Class<? extends LsPayResponse> getResponseClass() {
        return LsPayResponse.class;
    }

    public BaseRequest setExtendInfo(Map<String, ?> values) {
        extendInfos.clear();
        if (values != null) extendInfos.putAll(values);
        return this;
    }

    /**
     * 反射把对象自身字段拼成参数 Map
     *   - public 实例字段
     *   - 排除 extendInfos
     *   - 字段名按 ASCII 升序
     *   - 跳过 null / 空字符串
     */
    public Map<String, Object> toMap() {
        TreeMap<String, Object> map = new TreeMap<>();
        Class<?> c = getClass();
        while (c != null && c != Object.class) {
            for (Field f : c.getDeclaredFields()) {
                if (java.lang.reflect.Modifier.isStatic(f.getModifiers())) continue;
                if (!java.lang.reflect.Modifier.isPublic(f.getModifiers())) continue;
                String name = f.getName();
                if ("extendInfos".equals(name)) continue;
                try {
                    Object v = f.get(this);
                    if (v == null) continue;
                    if (v instanceof CharSequence && ((CharSequence) v).length() == 0) continue;
                    map.put(toUnderscore(name), v);
                } catch (IllegalAccessException ignore) {}
            }
            c = c.getSuperclass();
        }
        if (extendInfos != null && !extendInfos.isEmpty()) {
            for (Map.Entry<String, Object> e : extendInfos.entrySet()) {
                if (e.getValue() == null) continue;
                if (RESERVED.contains(e.getKey()) || map.containsKey(e.getKey())) {
                    throw new IllegalArgumentException("扩展参数不允许覆盖请求字段: " + e.getKey());
                }
                map.put(e.getKey(), e.getValue());
            }
        }
        return map;
    }

    protected static void required(String field, String value, int max) {
        if (value == null || value.trim().isEmpty()) throw new IllegalArgumentException(field + " 必填");
        if (value.length() > max) throw new IllegalArgumentException(field + " 长度不能超过 " + max);
    }

    protected static void optional(String field, String value, int max) {
        if (value != null && value.length() > max) throw new IllegalArgumentException(field + " 长度不能超过 " + max);
    }

    protected static void positive(String field, Number value) {
        if (value == null || value.longValue() <= 0) throw new IllegalArgumentException(field + " 必须是大于 0 的整数");
    }

    protected static void fixed(String field, String actual, String expected) {
        if (!expected.equals(actual)) throw new IllegalArgumentException(field + " 固定为 " + expected);
    }

    protected static void ipv4(String field, String value) {
        if (value == null || value.isEmpty()) return;
        String[] parts = value.split("\\.", -1);
        if (parts.length != 4) throw new IllegalArgumentException(field + " 必须是合法 IPv4 地址");
        for (String p : parts) {
            try {
                if (p.isEmpty() || Integer.parseInt(p) > 255 || (p.length() > 1 && p.charAt(0) == '0'))
                    throw new NumberFormatException();
            } catch (NumberFormatException e) {
                throw new IllegalArgumentException(field + " 必须是合法 IPv4 地址");
            }
        }
    }

    protected static void httpUrl(String field, String value) {
        if (value == null || value.isEmpty()) return;
        try {
            String scheme = URI.create(value).getScheme();
            if (!"http".equalsIgnoreCase(scheme) && !"https".equalsIgnoreCase(scheme)) throw new Exception();
        } catch (Exception e) {
            throw new IllegalArgumentException(field + " 必须是合法 HTTP(S) URL");
        }
    }

    protected static String jsonObject(Object value, String field) {
        if (value == null || "".equals(value)) return null;
        if (value instanceof JsonBuilder) return ((JsonBuilder) value).toJson();
        final JSONObject object;
        if (value instanceof String) {
            try { object = JSON.parseObject((String) value); }
            catch (RuntimeException e) { throw new IllegalArgumentException(field + " 必须是合法 JSON 对象字符串"); }
        } else if (value instanceof Map) {
            object = JSON.parseObject(JSON.toJSONString(value));
        } else {
            throw new IllegalArgumentException(field + " 只支持 Map、构造器或 JSON 对象字符串");
        }
        if (object == null) throw new IllegalArgumentException(field + " 必须是 JSON 对象");
        return JSON.toJSONString(object);
    }

    protected static String jsonValue(Object value) {
        if (value == null) return null;
        if (value instanceof String) return (String) value;
        if (value instanceof Map || value instanceof Collection) return JSON.toJSONString(value);
        throw new IllegalArgumentException("只支持字符串、Map 或 Collection");
    }

    protected static Map<String, Object> parseObject(String json, String field) {
        if (json == null || json.isEmpty()) return new LinkedHashMap<>();
        try {
            JSONObject object = JSON.parseObject(json);
            if (object == null) throw new RuntimeException();
            return new LinkedHashMap<>(object);
        } catch (RuntimeException e) {
            throw new IllegalArgumentException(field + " 必须是 JSON 对象字符串");
        }
    }

    /** mchOrderNo → mch_order_no */
    private static String toUnderscore(String name) {
        StringBuilder sb = new StringBuilder(name.length() + 4);
        for (int i = 0; i < name.length(); i++) {
            char ch = name.charAt(i);
            if (Character.isUpperCase(ch)) {
                if (i > 0) sb.append('_');
                sb.append(Character.toLowerCase(ch));
            } else {
                sb.append(ch);
            }
        }
        return sb.toString();
    }
}
