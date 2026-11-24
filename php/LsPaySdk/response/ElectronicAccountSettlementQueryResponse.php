<?php
/**
 * 电子账户结算记录查询响应。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;

class ElectronicAccountSettlementQueryResponse extends LsPayResponse
{
    /**
     * 返回结算记录列表。
     *
     * 兼容文档中的单条 data，以及常见的 records/list/rows 分页结构。
     */
    public function getRecords()
    {
        $data = $this->getData();
        if (!is_array($data)) {
            return [];
        }
        if ($this->isList($data)) {
            return $data;
        }
        foreach (['records', 'list', 'rows'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        return array_key_exists('trans_id', $data) ? [$data] : [];
    }

    public function getFirstRecord()
    {
        $records = $this->getRecords();
        return isset($records[0]) && is_array($records[0]) ? $records[0] : [];
    }

    public function getTransId() { return $this->recordValue('trans_id'); }
    public function getTransDate() { return $this->recordValue('trans_date'); }
    public function getSettleCycle() { return $this->recordValue('settle_cycle'); }
    public function getCardNo() { return $this->recordValue('card_no'); }
    public function getCardName() { return $this->recordValue('card_name'); }
    public function getBankCode() { return $this->recordValue('bank_code'); }
    public function getTransAmount() { return $this->recordValue('trans_amt'); }
    public function getFeeAmount() { return $this->recordValue('fee_amt'); }
    public function getTransStatus() { return $this->recordValue('trans_stat'); }
    public function getSettleType() { return $this->recordValue('settle_type'); }
    public function getSettleAbstract() { return $this->recordValue('settle_abstract'); }
    public function getSettleBatchNo() { return $this->recordValue('settle_batch_no'); }
    public function getSettleDescription() { return $this->recordValue('settle_desc'); }

    private function recordValue($key)
    {
        $record = $this->getFirstRecord();
        return array_key_exists($key, $record) && $record[$key] !== null
            ? (string)$record[$key]
            : null;
    }

    private function isList(array $value)
    {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}
