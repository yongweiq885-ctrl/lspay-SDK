package tech.lspay.sdk.config;

/**
 * 商户配置对象
 */
public class MerConfig {

    /** 商户号 */
    public String mchNo = "";

    /** 应用 ID */
    public String appId = "";

    /** MD5 签名密钥 */
    public String md5Key = "";

    /** 默认异步通知地址（可空，下单时可覆盖） */
    public String notifyUrl = "";

    /** 默认同步跳转地址（可空，下单时可覆盖） */
    public String returnUrl = "";
}
