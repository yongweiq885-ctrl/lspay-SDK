package tech.lspay.sdk.exceptions;

import java.util.Map;

/** 网关返回业务失败（含 code/msg） */
public class LsPayBizException extends LsPayException {
    public final String code;
    public final String msg;
    public final Map<String, Object> raw;

    public LsPayBizException(String code, String msg, Map<String, Object> raw) {
        super("[LsPay " + code + "] " + msg);
        this.code = code;
        this.msg = msg;
        this.raw = raw;
    }
}
