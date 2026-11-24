package tech.lspay.sdk.exceptions;

/** 立收通 SDK 异常基类 */
public class LsPayException extends RuntimeException {
    public LsPayException(String msg) { super(msg); }
    public LsPayException(String msg, Throwable cause) { super(msg, cause); }
}
