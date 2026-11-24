package tech.lspay.sdk.core;

import com.alibaba.fastjson2.JSONObject;

import java.util.HashMap;
import java.util.Map;

/**
 * 立收通响应封装
 *
 * 典型返回：
 *   {
 *     "status": 200,        顶层 status（200 表示处理成功）
 *     "msg":  "success",
 *     "data": { ... },      业务数据，业务结果看 trans_status / order_state / state
 *     "sign": "..."
 *   }
 */
public class LsPayResponse {

    private final String raw;
    private final Map<String, Object> json;

    public LsPayResponse(String raw) {
        this.raw = raw;
        if (raw == null || raw.isEmpty()) {
            this.json = new HashMap<>();
        } else {
            JSONObject obj = JSONObject.parseObject(raw);
            this.json = obj == null ? new HashMap<>() : new HashMap<>(obj);
        }
    }

    public LsPayResponse(Map<String, Object> value) {
        this.raw = com.alibaba.fastjson2.JSON.toJSONString(value);
        this.json = value == null ? new HashMap<String, Object>() : new HashMap<>(value);
    }

    public String getRaw() { return raw; }

    public Map<String, Object> getMap() { return json; }

    public String getStatus() {
        Object v = json.get("status");
        if (v == null) v = json.get("code");
        if (v == null) v = json.get("retCode");
        return v == null ? "" : String.valueOf(v);
    }

    /** @deprecated 使用 getStatus() */
    @Deprecated
    public String getCode() { return getStatus(); }

    public String getMsg() {
        Object v = json.get("msg");
        if (v == null) v = json.get("retMsg");
        return v == null ? "" : String.valueOf(v);
    }

    /** 同步响应签名仅透传，不执行验签。 */
    public String getSign() {
        Object v = json.get("sign");
        return v == null ? "" : String.valueOf(v);
    }

    /** 接口级失败：正式协议 status != 200；兼容旧 code=0/SUCCESS。 */
    public boolean isError() {
        String status = getStatus().toUpperCase();
        return !("200".equals(status) || "0".equals(status) || "SUCCESS".equals(status));
    }

    public String getErrorInfo() {
        return "[" + getStatus() + "] " + getMsg();
    }

    /** 业务数据 data 节点 */
    @SuppressWarnings("unchecked")
    public Map<String, Object> getData() {
        Object d = json.get("data");
        if (d instanceof Map) return (Map<String, Object>) d;
        if (d instanceof String && !((String) d).isEmpty()) {
            try {
                JSONObject parsed = JSONObject.parseObject((String) d);
                if (parsed != null) return parsed;
            } catch (RuntimeException ignored) {
                // 非法 JSON data 按空对象处理，原始响应仍可通过 getRaw() 获取。
            }
        }
        return new HashMap<>();
    }

    protected Object dataValue(String key) {
        Map<String, Object> data = getData();
        return data.containsKey(key) ? data.get(key) : json.get(key);
    }
    protected String stringValue(String key) {
        Object value = dataValue(key);
        return value == null ? null : String.valueOf(value);
    }
    protected Long longValue(String key) {
        Object value = dataValue(key);
        if (value == null || "".equals(value)) return null;
        try { return Long.valueOf(String.valueOf(value)); }
        catch (NumberFormatException e) { return null; }
    }
    @SuppressWarnings("unchecked")
    protected java.util.List<Map<String, Object>> listValue(String key) {
        Object value = dataValue(key);
        return value instanceof java.util.List
                ? (java.util.List<Map<String, Object>>) value
                : new java.util.ArrayList<Map<String, Object>>();
    }
}
