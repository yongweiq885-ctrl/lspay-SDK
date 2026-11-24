<?php
/**
 * 立收通 SDK 异常体系
 */
namespace LsPaySdk\exceptions;

class LsPayException extends \RuntimeException {}

class LsPayConfigException extends LsPayException {}

/** 请求参数不符合立收通接口契约。 */
class LsPayValidationException extends LsPayException {}

class LsPayNetworkException extends LsPayException
{
    public $httpStatus;
    public $curlErrno;
    public $responseDetail;

    public function __construct(
        $message,
        $httpStatus = 0,
        $curlErrno = 0,
        $responseDetail = null
    ) {
        parent::__construct($message);
        $this->httpStatus = (int)$httpStatus;
        $this->curlErrno = (int)$curlErrno;
        $this->responseDetail = $responseDetail;
    }
}

/** 网关返回无法按协议解析。 */
class LsPayProtocolException extends LsPayException {}

class LsPaySignException extends LsPayException {}

class LsPayBizException extends LsPayException
{
    /** 网关业务错误码 */
    public $code;
    /** 网关业务错误消息 */
    public $msg;
    /** 网关返回的原始数据 */
    public $raw;

    public function __construct($code, $msg, array $raw = [])
    {
        parent::__construct(sprintf('[LsPay %s] %s', $code, $msg));
        $this->code = $code;
        $this->msg  = $msg;
        $this->raw  = $raw;
    }
}
