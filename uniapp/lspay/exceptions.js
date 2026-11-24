/**
 * 立收通 SDK 异常体系
 */
class LsPayException extends Error {
  constructor(msg) {
    super(msg);
    this.name = 'LsPayException';
  }
}

class LsPayConfigException extends LsPayException {
  constructor(msg) { super(msg); this.name = 'LsPayConfigException'; }
}

class LsPayNetworkException extends LsPayException {
  constructor(msg) { super(msg); this.name = 'LsPayNetworkException'; }
}

class LsPaySignException extends LsPayException {
  constructor(msg) { super(msg); this.name = 'LsPaySignException'; }
}

class LsPayValidationException extends LsPayException {
  constructor(msg) { super(msg); this.name = 'LsPayValidationException'; }
}

class LsPayBizException extends LsPayException {
  constructor(code, msg, raw) {
    super(`[LsPay ${code}] ${msg}`);
    this.name = 'LsPayBizException';
    this.code = code;
    this.bizMsg = msg;
    this.raw = raw;
  }
}

module.exports = {
  LsPayException,
  LsPayConfigException,
  LsPayNetworkException,
  LsPaySignException,
  LsPayValidationException,
  LsPayBizException,
};
