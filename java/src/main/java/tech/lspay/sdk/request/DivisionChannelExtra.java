package tech.lspay.sdk.request;

import java.util.ArrayList;
import java.util.List;

public class DivisionChannelExtra extends JsonBuilder {
    public DivisionChannelExtra addDivisionReceiver(DivisionReceiver value) {
        List<Object> list = (List<Object>) values.get("div_list");
        if (list == null) { list = new ArrayList<>(); values.put("div_list", list); }
        list.add(value.toMap());
        return this;
    }
    public DivisionChannelExtra setDivisionReceivers(List<DivisionReceiver> receivers) {
        List<Object> list = new ArrayList<>();
        if (receivers != null) for (DivisionReceiver receiver : receivers) list.add(receiver.toMap());
        values.put("div_list", list);
        return this;
    }
    @Override public DivisionChannelExtra set(String key, Object value) { super.set(key, value); return this; }
}
