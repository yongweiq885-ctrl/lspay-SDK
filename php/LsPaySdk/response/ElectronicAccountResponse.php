<?php
/**
 * 电子账户交易响应基类。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;
use LsPaySdk\enums\ElectronicAccountTransStatusEnum;

abstract class ElectronicAccountResponse extends LsPayResponse
{
    public function getTransStatus()
    {
        return $this->accountValue('trans_status');
    }

    public function isError()
    {
        $raw = $this->getRaw();
        if (array_key_exists('status', $raw)
            || array_key_exists('code', $raw)
            || array_key_exists('retCode', $raw)) {
            return parent::isError();
        }
        return $this->getTransStatus() === null
            || $this->getTransStatus() === ElectronicAccountTransStatusEnum::FAILED;
    }

    public function isSuccessful()
    {
        return !$this->isError()
            && $this->getTransStatus() === ElectronicAccountTransStatusEnum::SUCCEEDED;
    }

    public function isProcessing()
    {
        return !$this->isError()
            && $this->getTransStatus() === ElectronicAccountTransStatusEnum::PROCESSING;
    }

    protected function accountValue($key)
    {
        $data = $this->getData();
        if (is_array($data) && array_key_exists($key, $data)) {
            return $data[$key] === null ? null : (string)$data[$key];
        }
        $raw = $this->getRaw();
        return array_key_exists($key, $raw) && $raw[$key] !== null
            ? (string)$raw[$key]
            : null;
    }
}
