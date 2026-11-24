<?php
/**
 * 电子账户批量提现查询响应。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;
use LsPaySdk\enums\ElectronicAccountWithdrawStatusEnum;

class ElectronicAccountBatchWithdrawQueryResponse extends LsPayResponse
{
    public function getCount() { return $this->pageInteger('count'); }
    public function getPageSize() { return $this->pageInteger('page_size'); }
    public function getPageNum() { return $this->pageInteger('page_num'); }
    public function getChannelStatus() { return $this->pageValue('status'); }

    public function getRecords()
    {
        $data = $this->getData();
        return is_array($data)
            && isset($data['data'])
            && is_array($data['data'])
                ? $data['data']
                : [];
    }

    public function getFirstRecord()
    {
        $records = $this->getRecords();
        return isset($records[0]) && is_array($records[0]) ? $records[0] : [];
    }

    public function getSettleDate() { return $this->recordValue('settle_date'); }
    public function getCardNo() { return $this->recordValue('card_no'); }
    public function getCardName() { return $this->recordValue('card_name'); }
    public function getBankName() { return $this->recordValue('bank_name'); }
    public function getBatchTransType() { return $this->recordValue('batch_trans_type'); }
    public function getCashAmount() { return $this->recordValue('cash_amt'); }
    public function getFeeAmount() { return $this->recordValue('fee_amt'); }
    public function getFeeFlag() { return $this->recordValue('fee_flag'); }
    public function getOriginalRequestDate() { return $this->recordValue('org_req_date'); }
    public function getOrderId() { return $this->recordValue('order_id'); }
    public function getTransStatus() { return $this->recordValue('trans_status'); }
    public function getRemark() { return $this->recordValue('remark'); }
    public function getTransDescription() { return $this->recordValue('trans_desc'); }

    public function isFirstRecordPending()
    {
        return !$this->isError()
            && $this->getTransStatus() === ElectronicAccountWithdrawStatusEnum::PENDING;
    }

    public function isFirstRecordFailed()
    {
        return !$this->isError()
            && $this->getTransStatus() === ElectronicAccountWithdrawStatusEnum::FAILED;
    }

    public function isFirstRecordSucceeded()
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

    private function pageInteger($key)
    {
        $value = $this->pageValue($key);
        return $value === null ? null : (int)$value;
    }

    private function pageValue($key)
    {
        $data = $this->getData();
        return is_array($data)
            && array_key_exists($key, $data)
            && $data[$key] !== null
                ? (string)$data[$key]
                : null;
    }

    private function recordValue($key)
    {
        $record = $this->getFirstRecord();
        return array_key_exists($key, $record) && $record[$key] !== null
            ? (string)$record[$key]
            : null;
    }
}
