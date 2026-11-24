package tech.lspay.sdk.request;
public final class BalancePayChannelExtra extends DivisionChannelExtra {
    public BalancePayChannelExtra setTransOut(String value) {
        if (value == null || value.trim().isEmpty()) throw new IllegalArgumentException("trans_out 必填");
        set("trans_out", value.trim()); return this;
    }
}
