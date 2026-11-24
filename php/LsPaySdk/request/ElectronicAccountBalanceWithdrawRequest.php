<?php
/**
 * 电子账户余额提现请求。
 *
 * 端点：/gateway/user/balance/withdraw
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class ElectronicAccountBalanceWithdrawRequest extends BaseRequest
{
    protected $orderId;
    protected $wayCode = WayCodeEnum::BALANCE_WITHDRAW;
    protected $accountNo;
    protected $withdrawAmount;

    public function getFunctionCode()
    {
        return EndpointEnum::ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\ElectronicAccountBalanceWithdrawResponse::class;
    }

    public function getOrderId() { return $this->orderId; }
    public function getWayCode() { return $this->wayCode; }
    public function getAccountNo() { return $this->accountNo; }
    public function getWithdrawAmount() { return $this->withdrawAmount; }

    public function setOrderId($v) { $this->orderId = trim((string)$v); return $this; }
    public function setWayCode($v) { $this->wayCode = trim((string)$v); return $this; }
    public function setAccountNo($v) { $this->accountNo = trim((string)$v); return $this; }
    public function setWithdrawAmount($v)
    {
        $this->withdrawAmount = is_string($v) || is_numeric($v)
            ? trim((string)$v)
            : $v;
        return $this;
    }

    public function validate()
    {
        $this->assertRequiredString('order_id', $this->orderId, 30);
        if ($this->wayCode !== WayCodeEnum::BALANCE_WITHDRAW) {
            throw new LsPayValidationException(
                'way_code 固定为 ' . WayCodeEnum::BALANCE_WITHDRAW
            );
        }
        $this->assertRequiredString('account_no', $this->accountNo, 32);

        if (!is_string($this->withdrawAmount)
            || strlen($this->withdrawAmount) > 32
            || !preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $this->withdrawAmount)
            || !preg_match('/[1-9]/', $this->withdrawAmount)) {
            throw new LsPayValidationException(
                'withdraw_amount 必须是大于 0 且最多保留 2 位小数的元金额'
            );
        }
    }

    private function assertRequiredString($field, $value, $maxLength)
    {
        if ($value === null || trim((string)$value) === '') {
            throw new LsPayValidationException("$field 必填");
        }
        $length = function_exists('mb_strlen')
            ? mb_strlen((string)$value, 'UTF-8')
            : strlen((string)$value);
        if ($length > $maxLength) {
            throw new LsPayValidationException("$field 长度不能超过 $maxLength");
        }
    }
}
