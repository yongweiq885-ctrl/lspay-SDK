<?php
/**
 * 电子账户单笔提现查询响应。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;
use LsPaySdk\enums\ElectronicAccountWithdrawStatusEnum;

class ElectronicAccountSingleWithdrawQueryResponse extends LsPayResponse
{
    public function getWithdrawDate() { return $this->dataValue('withdraw_date'); }
    public function getOrderId() { return $this->dataValue('order_id'); }
    public function getCashAmount() { return $this->dataValue('cash_amt'); }
    public function getFeeAmount() { return $this->dataValue('fee_amt'); }
    public function getTransStatus() { return $this->dataValue('trans_status'); }
    public function getTransDescription() { return $this->dataValue('trans_desc'); }
    public function getChannelStatus() { return $this->dataValue('status'); }
    public function getChannelMessage() { return $this->dataValue('err_msg'); }

    public function isPending()
    {
        return !$this->isError()
            && $this->getTransStatus() === ElectronicAccountWithdrawStatusEnum::PENDING;
    }

    public function isFailed()
    {
        return !$this->isError()
            && $this->getTransStatus() === ElectronicAccountWithdrawStatusEnum::FAILED;
    }

    public function isSucceeded()
    {
        return !$this->isError()
            && $this->getTransStatus() === ElectronicAccountWithdrawStatusEnum::SUCCEEDED;
    }

    /** succeess 是部分提现接口定义的成功状态；同时兼容相关接口的 success。 */
    public function isChannelSuccessful()
    {
        return in_array(
            strtolower((string)$this->getChannelStatus()),
            ['success', 'succeess'],
            true
        );
    }

    private function dataValue($key)
    {
        $data = $this->getData();
        return is_array($data)
            && array_key_exists($key, $data)
            && $data[$key] !== null
                ? (string)$data[$key]
                : null;
    }
}
