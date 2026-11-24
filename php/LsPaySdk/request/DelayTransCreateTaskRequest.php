<?php
/**
 * 延时分账订单转账请求。
 *
 * 端点：/gateway/delaytrans/createtask
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class DelayTransCreateTaskRequest extends BaseRequest
{
    protected $accountNo;
    protected $accountName;
    protected $orderNo;
    protected $amount;
    protected $notifyUrl;
    protected $remark;

    public function getFunctionCode()
    {
        return EndpointEnum::DELAY_TRANS_CREATE_TASK;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\DelayTransCreateTaskResponse::class;
    }

    public function getAccountNo() { return $this->accountNo; }
    public function getAccountName() { return $this->accountName; }
    public function getOrderNo() { return $this->orderNo; }
    public function getAmount() { return $this->amount; }
    public function getNotifyUrl() { return $this->notifyUrl; }
    public function getRemark() { return $this->remark; }

    public function setAccountNo($value)
    {
        $this->accountNo = trim((string)$value);
        return $this;
    }

    public function setAccountName($value)
    {
        $this->accountName = trim((string)$value);
        return $this;
    }

    public function setOrderNo($value)
    {
        $this->orderNo = trim((string)$value);
        return $this;
    }

    public function setAmount($value)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new LsPayValidationException('amount 必须是整数');
        }
        $this->amount = (int)$value;
        return $this;
    }

    public function setNotifyUrl($value)
    {
        $this->notifyUrl = trim((string)$value);
        return $this;
    }

    public function setRemark($value)
    {
        $this->remark = (string)$value;
        return $this;
    }

    public function validate()
    {
        $this->assertRequiredString('account_no', $this->accountNo, 30);
        $this->assertRequiredString('account_name', $this->accountName, 30);
        $this->assertRequiredString('order_no', $this->orderNo, 30);
        if (!is_int($this->amount) || $this->amount <= 0) {
            throw new LsPayValidationException(
                'amount 必须是大于 0 的整数（单位：分）'
            );
        }
        $this->assertOptionalString('notify_url', $this->notifyUrl, 64);
        $this->assertOptionalString('remark', $this->remark, 256);

        if ($this->notifyUrl !== null && $this->notifyUrl !== '') {
            $scheme = strtolower((string)parse_url($this->notifyUrl, PHP_URL_SCHEME));
            if (filter_var($this->notifyUrl, FILTER_VALIDATE_URL) === false
                || !in_array($scheme, ['http', 'https'], true)) {
                throw new LsPayValidationException(
                    'notify_url 必须是合法 HTTP(S) URL'
                );
            }
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
