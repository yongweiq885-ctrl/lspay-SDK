<?php
/**
 * 电子账户用户查询请求。
 *
 * 端点：/gateway/user/query
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class ElectronicAccountUserQueryRequest extends BaseRequest
{
    const WAY_CODE = 'APPLY';

    protected $wayCode = self::WAY_CODE;
    protected $accountNo;
    protected $clientIp;

    public function getFunctionCode()
    {
        return EndpointEnum::ELECTRONIC_ACCOUNT_USER_QUERY;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\ElectronicAccountUserQueryResponse::class;
    }

    public function getWayCode() { return $this->wayCode; }
    public function getAccountNo() { return $this->accountNo; }
    public function getClientIp() { return $this->clientIp; }

    public function setWayCode($v) { $this->wayCode = trim((string)$v); return $this; }
    public function setAccountNo($v) { $this->accountNo = trim((string)$v); return $this; }
    public function setClientIp($v) { $this->clientIp = trim((string)$v); return $this; }

    public function validate()
    {
        if ($this->wayCode !== self::WAY_CODE) {
            throw new LsPayValidationException('way_code 固定为 ' . self::WAY_CODE);
        }
        $this->assertRequiredString('account_no', $this->accountNo, 32);
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
