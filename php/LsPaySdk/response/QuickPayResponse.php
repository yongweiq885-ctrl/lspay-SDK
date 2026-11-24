<?php
/**
 * 快捷支付交易响应基类。
 *
 * 兼容公共 status/msg/data 外层，以及官方示例中的直接业务字段格式。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;
use LsPaySdk\enums\QuickPayTransStatusEnum;

abstract class QuickPayResponse extends LsPayResponse
{
    public function getTransStatus()
    {
        return $this->quickPayValue('trans_status');
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
            || $this->getTransStatus() === QuickPayTransStatusEnum::FAILED;
    }

    public function isAccepted()
    {
        return !$this->isError();
    }

    public function isSuccessful()
    {
        return !$this->isError()
            && $this->getTransStatus() === QuickPayTransStatusEnum::SUCCEEDED;
    }

    public function isProcessing()
    {
        return !$this->isError()
            && $this->getTransStatus() === QuickPayTransStatusEnum::PROCESSING;
    }

    protected function quickPayValue($key)
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
