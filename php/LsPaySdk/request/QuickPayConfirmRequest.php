<?php
/**
 * 快捷支付确认请求。
 *
 * 端点：/gateway/quickpay/payconfirm
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class QuickPayConfirmRequest extends BaseRequest
{
    protected $wayCode = WayCodeEnum::BANK_FAST;
    protected $clientIp;
    protected $payOrderId;
    protected $mchOrderNo;
    protected $verifyCode;

    public function getFunctionCode() { return EndpointEnum::QUICK_PAY_CONFIRM; }
    public function getResponseClass()
    {
        return \LsPaySdk\response\QuickPayConfirmResponse::class;
    }

    public function getWayCode() { return $this->wayCode; }
    public function getClientIp() { return $this->clientIp; }
    public function getPayOrderId() { return $this->payOrderId; }
    public function getMchOrderNo() { return $this->mchOrderNo; }
    public function getVerifyCode() { return $this->verifyCode; }

    public function setWayCode($value)
    {
        $value = trim((string)$value);
        if ($value !== WayCodeEnum::BANK_FAST) {
            throw new LsPayValidationException('way_code 固定为 BANK_FAST');
        }
        $this->wayCode = $value;
        return $this;
    }

    public function setClientIp($value) { $this->clientIp = trim((string)$value); return $this; }
    public function setPayOrderId($value) { $this->payOrderId = trim((string)$value); return $this; }
    public function setMchOrderNo($value) { $this->mchOrderNo = trim((string)$value); return $this; }
    public function setVerifyCode($value) { $this->verifyCode = trim((string)$value); return $this; }

    public function validate()
    {
        if ($this->wayCode !== WayCodeEnum::BANK_FAST) {
            throw new LsPayValidationException('way_code 固定为 BANK_FAST');
        }
        $this->assertOptionalString('client_ip', $this->clientIp, 32);
        if ($this->clientIp !== null && $this->clientIp !== ''
            && filter_var($this->clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new LsPayValidationException('client_ip 必须是合法 IPv4 地址');
        }
        $this->assertOptionalString('pay_order_id', $this->payOrderId, 30);
        $this->assertOptionalString('mch_order_no', $this->mchOrderNo, 30);
        if (($this->payOrderId === null || $this->payOrderId === '')
            && ($this->mchOrderNo === null || $this->mchOrderNo === '')) {
            throw new LsPayValidationException(
                'pay_order_id 与 mch_order_no 至少填写一个'
            );
        }
        if (!preg_match('/^\d{6}$/', (string)$this->verifyCode)) {
            throw new LsPayValidationException('verify_code 必须是 6 位数字');
        }
    }

    private function assertOptionalString($field, $value, $maxLength)
    {
        if ($value === null || $value === '') {
            return;
        }
        if (!is_string($value) && !is_numeric($value)) {
            throw new LsPayValidationException("$field 必须是字符串");
        }
        $length = function_exists('mb_strlen')
            ? mb_strlen((string)$value, 'UTF-8')
            : strlen((string)$value);
        if ($length > $maxLength) {
            throw new LsPayValidationException("$field 长度不能超过 $maxLength");
        }
    }
}
