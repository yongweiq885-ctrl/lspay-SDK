<?php
/**
 * 电子账户余额提现响应。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;

class ElectronicAccountBalanceWithdrawResponse extends LsPayResponse
{
    public function getAcceptanceMessage()
    {
        return $this->dataValue('err_msg');
    }

    public function getAcceptanceStatus()
    {
        return $this->dataValue('status');
    }

    public function isAccepted()
    {
        return !$this->isError()
            && in_array(
                strtolower((string)$this->getAcceptanceStatus()),
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
