package tech.lspay.sdk.request;

import java.util.LinkedHashMap;
import java.util.Map;

public final class DivisionReceiver {
    private String transIn;
    private Long divAmt;
    private String description;

    public DivisionReceiver() {}
    public DivisionReceiver(String transIn) { this.transIn = transIn; }
    public DivisionReceiver setTransIn(String value) { this.transIn = value == null ? null : value.trim(); return this; }
    public DivisionReceiver setDivAmt(long value) {
        if (value <= 0) throw new IllegalArgumentException("div_amt 必须大于 0");
        this.divAmt = value; return this;
    }
    public DivisionReceiver setDescription(String value) { this.description = value; return this; }
    public Map<String, Object> toMap() {
        if (transIn == null || transIn.isEmpty()) throw new IllegalArgumentException("div_list.trans_in 必填");
        Map<String, Object> map = new LinkedHashMap<>();
        map.put("trans_in", transIn);
        if (divAmt != null) map.put("div_amt", divAmt);
        if (description != null) map.put("description", description);
        return map;
    }
}
