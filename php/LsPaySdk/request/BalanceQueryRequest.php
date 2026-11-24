<?php
/**
 * 电子账户余额查询请求。
 *
 * 端点：/gateway/user/balance/query
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class BalanceQueryRequest extends BaseRequest
{
    protected $orderId;
    protected $wayCode = WayCodeEnum::BALANCE_QUERY;
    protected $clientIp;
    protected $accountNo;

    public function getFunctionCode()
    {
        return EndpointEnum::BALANCE_QUERY;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\BalanceQueryResponse::class;
    }

    public function getOrderId() { return $this->orderId; }
    public function getWayCode() { return $this->wayCode; }
    public function getClientIp() { return $this->clientIp; }
    public function getAccountNo() { return $this->accountNo; }

    public function setOrderId($v) { $this->orderId = trim((string)$v); return $this; }
    public function setWayCode($v) { $this->wayCode = trim((string)$v); return $this; }
    public function setClientIp($v) { $this->clientIp = trim((string)$v); return $this; }
    public function setAccountNo($v) { $this->accountNo = trim((string)$v); return $this; }

    public function validate()
    {
        $this->assertRequiredString('order_id', $this->orderId, 30);
        if ($this->wayCode !== WayCodeEnum::BALANCE_QUERY) {
            throw new LsPayValidationException(
                'way_code 固定为 ' . WayCodeEnum::BALANCE_QUERY
            );
        }
        $this->assertRequiredString('account_no', $this->accountNo, 256);
        $this->assertOptionalString('client_ip', $this->clientIp, 32);
        if ($this->clientIp !== null && $this->clientIp !== ''
            && filter_var($this->clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new LsPayValidationException('client_ip 必须是合法 IPv4 地址');
        }
    }

    private function assertRequiredString($field, $value, $maxLength)
    {
        if ($value === null || trim((string)$value) === '') {
            throw new LsPayValidationException("$field 必填");
        }
        $this->assertOptionalString($field, $value, $maxLength);
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
