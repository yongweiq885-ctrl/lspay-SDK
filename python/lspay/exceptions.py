"""立收通 SDK 异常体系"""


class LsPayException(Exception):
    """SDK 异常基类"""


class LsPayConfigException(LsPayException):
    """配置错误（密钥/商户号/参数缺失）"""


class LsPayNetworkException(LsPayException):
    """网络 / HTTP 错误"""


class LsPaySignException(LsPayException):
    """签名 / 验签失败"""


class LsPayValidationException(LsPayException):
    """请求或通知参数不符合协议"""


class LsPayProtocolException(LsPayException):
    """网关响应不是合法协议数据"""


class LsPayBizException(LsPayException):
    """网关返回业务失败"""

    def __init__(self, code: str, msg: str, raw=None):
        super().__init__(f"[LsPay {code}] {msg}")
        self.code = code
        self.msg = msg
        self.raw = raw
