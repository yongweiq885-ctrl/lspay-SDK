package tech.lspay.sdk.example;

/** Demo 演示用常量集合 */
public final class DemoConstants {
    private DemoConstants() {}

    public static final String ORDER_PREFIX  = "JT";
    public static final String REFUND_PREFIX = "JR";

    public static final long DEFAULT_AMOUNT_FEN = 1L;
    public static final String DEFAULT_SUBJECT  = "立收通 SDK Demo 商品";
    public static final String DEFAULT_BODY     = "立收通 SDK Demo 商品描述";

    public static String genOrderNo() {
        return ORDER_PREFIX + System.currentTimeMillis();
    }

    public static String genRefundNo() {
        return REFUND_PREFIX + System.currentTimeMillis();
    }
}
