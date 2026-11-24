package tech.lspay.sdk.exceptions;

/** 签名/验签失败 */
public class LsPaySignException extends LsPayException {
    public LsPaySignException(String msg) { super(msg); }
}
