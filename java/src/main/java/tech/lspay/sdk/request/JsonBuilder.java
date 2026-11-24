package tech.lspay.sdk.request;

import com.alibaba.fastjson2.JSON;

import java.util.LinkedHashMap;
import java.util.Map;

/** channel_extra 嵌套对象的通用 JDK8 构造器。 */
public class JsonBuilder {
    protected final Map<String, Object> values = new LinkedHashMap<>();

    public JsonBuilder set(String key, Object value) {
        if (key == null || key.trim().isEmpty()) throw new IllegalArgumentException("参数名不能为空");
        if (value == null || "".equals(value)) values.remove(key);
        else if (value instanceof JsonBuilder) values.put(key, ((JsonBuilder) value).toMap());
        else values.put(key, value);
        return this;
    }

    public Map<String, Object> toMap() {
        return new LinkedHashMap<>(values);
    }

    public String toJson() {
        return JSON.toJSONString(values);
    }
}
