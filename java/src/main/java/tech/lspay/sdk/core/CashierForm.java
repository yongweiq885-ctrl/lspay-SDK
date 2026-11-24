package tech.lspay.sdk.core;

import java.util.Collections;
import java.util.LinkedHashMap;
import java.util.Map;

/**
 * pay_data_type=cashier 的收银台表单：由用户浏览器把 fields 以 POST 提交到 action。
 */
public final class CashierForm {

    private final String action;
    private final Map<String, String> fields;

    CashierForm(String action, Map<String, Object> signed) {
        this.action = action;
        Map<String, String> copy = new LinkedHashMap<>();
        for (Map.Entry<String, Object> e : signed.entrySet()) {
            copy.put(e.getKey(), String.valueOf(e.getValue()));
        }
        this.fields = Collections.unmodifiableMap(copy);
    }

    public String getAction() { return action; }

    public String getMethod() { return "POST"; }

    public Map<String, String> getFields() { return fields; }

    /** 自动提交到立收通收银台的 HTML 表单。 */
    public String toHtml() {
        StringBuilder sb = new StringBuilder();
        sb.append("<form id=\"lspayCashierForm\" method=\"POST\" action=\"").append(escape(action)).append("\">");
        for (Map.Entry<String, String> e : fields.entrySet()) {
            sb.append("<input type=\"hidden\" name=\"").append(escape(e.getKey()))
              .append("\" value=\"").append(escape(e.getValue())).append("\">");
        }
        return sb.append("</form><script>document.getElementById(\"lspayCashierForm\").submit();</script>").toString();
    }

    private static String escape(String s) {
        return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
                .replace("\"", "&quot;").replace("'", "&#39;");
    }
}
