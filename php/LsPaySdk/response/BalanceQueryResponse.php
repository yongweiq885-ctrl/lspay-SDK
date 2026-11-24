<?php
/**
 * 电子账户余额查询响应。
 *
 * 兼容标准 status/data 包装及文档示例中的扁平响应。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;
use LsPaySdk\enums\ElectronicAccountBalanceStatusEnum;

class BalanceQueryResponse extends LsPayResponse
{
    public function getData()
    {
        $data = parent::getData();
        $raw = $this->getRaw();
        if (is_array($data) && $data === [] && array_key_exists('acct_type', $raw)) {
            return $raw;
        }
        return $data;
    }

    public function isError()
    {
        $raw = $this->getRaw();
        if (!array_key_exists('status', $raw)
            && array_key_exists('acct_type', $raw)) {
            return false;
        }
        return parent::isError();
    }

    public function getAccountType() { return $this->balanceValue('acct_type'); }
    public function getAccountStatus() { return $this->balanceValue('acct_stat'); }
    public function getBalanceAmount() { return $this->balanceValue('balance_amt'); }
    public function getAvailableBalance() { return $this->balanceValue('avl_balance'); }
    public function getFrozenBalance() { return $this->balanceValue('frz_bal'); }
    public function getHistoricalBalance() { return $this->balanceValue('dm_amt'); }

    public function isNormal()
    {
        return !$this->isError()
            && $this->getAccountStatus() === ElectronicAccountBalanceStatusEnum::NORMAL;
    }

    private function balanceValue($key)
    {
        $data = $this->getData();
        return is_array($data)
            && array_key_exists($key, $data)
            && $data[$key] !== null
                ? (string)$data[$key]
                : null;
    }
}
