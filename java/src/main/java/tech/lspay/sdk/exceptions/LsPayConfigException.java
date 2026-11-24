package tech.lspay.sdk.exceptions;

/** 配置错误（密钥、商户号、参数缺失） */
public class LsPayConfigException extends LsPayException {
    public LsPayConfigException(String msg) { super(msg); }
}
