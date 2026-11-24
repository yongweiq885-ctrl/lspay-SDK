<?php
/**
 * 电子账户单笔提现查询请求。
 *
 * 端点：/gateway/user/balance/withdraw/single/query
 */
namespace LsPaySdk\request;

use DateTime;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class ElectronicAccountSingleWithdrawQueryRequest extends BaseRequest
{
    protected $wayCode = WayCodeEnum::WITHDRAW_SINGLE_QUERY;
    protected $accountNo;
    protected $withdrawDate;
    protected $orderId;

    public function getFunctionCode()
    {
        return EndpointEnum::ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\ElectronicAccountSingleWithdrawQueryResponse::class;
    }

    public function getWayCode() { return $this->wayCode; }
    public function getAccountNo() { return $this->accountNo; }
    public function getWithdrawDate() { return $this->withdrawDate; }
    public function getOrderId() { return $this->orderId; }

    public function setWayCode($v) { $this->wayCode = trim((string)$v); return $this; }
    public function setAccountNo($v) { $this->accountNo = trim((string)$v); return $this; }
    public function setWithdrawDate($v) { $this->withdrawDate = trim((string)$v); return $this; }
    public function setOrderId($v) { $this->orderId = trim((string)$v); return $this; }

    public function validate()
    {
        if ($this->wayCode !== WayCodeEnum::WITHDRAW_SINGLE_QUERY) {
            throw new LsPayValidationException(
                'way_code 固定为 ' . WayCodeEnum::WITHDRAW_SINGLE_QUERY
            );
        }
        $this->assertRequiredString('account_no', $this->accountNo, 32);
        $this->assertDate('withdraw_date', $this->withdrawDate);
        $this->assertRequiredString('order_id', $this->orderId, 30);
    }

    private function assertDate($field, $value)
    {
        if (!is_string($value) && !is_numeric($value)) {
            throw new LsPayValidationException("$field 必须是 yyyyMMdd 格式日期");
        }
        $value = (string)$value;
        $date = DateTime::createFromFormat('!Ymd', $value);
        $errors = DateTime::getLastErrors();
        if (strlen($value) !== 8 || !$date
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Ymd') !== $value) {
            throw new LsPayValidationException("$field 必须是合法的 yyyyMMdd 格式日期");
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
