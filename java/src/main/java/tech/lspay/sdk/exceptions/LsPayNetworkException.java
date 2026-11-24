package tech.lspay.sdk.exceptions;

/** 网络/HTTP 错误 */
public class LsPayNetworkException extends LsPayException {
    public LsPayNetworkException(String msg, Throwable cause) { super(msg, cause); }
}
