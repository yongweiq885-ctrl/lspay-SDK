<?php
/**
 * 延时分账交易确认响应。
 *
 * status=200 表示接口调用成功；交易确认结果以 data.trans_status=S 为准。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;

class DelayTransConfirmResponse extends LsPayResponse
{
    public function getUnconfirmAmount()
    {
        return $this->integerValue('unconfirm_amount');
    }

    public function getConfirmedAmount()
    {
        return $this->integerValue('confirmed_amount');
    }

    public function getConfirmOrderId()
    {
        return $this->dataValue('confirm_order_id');
    }

    public function getBatchOrderId()
    {
        return $this->dataValue('batch_order_id');
    }

    public function getTransStatus()
    {
        return $this->dataValue('trans_status');
    }

    public function getErrCode()
    {
        return $this->dataValue('err_code');
    }

    public function getErrMsg()
    {
        return $this->dataValue('err_msg');
    }

    /** 接口调用成功，不代表交易确认业务成功。 */
    public function isAccepted()
    {
        return !$this->isError();
    }

    public function isSuccessful()
    {
        return !$this->isError() && $this->getTransStatus() === 'S';
    }

    private function integerValue($key)
    {
        $value = $this->dataValue($key);
        return $value === null || $value === '' ? null : (int)$value;
    }

    private function dataValue($key)
    {
        $data = $this->getData();
        return is_array($data) && array_key_exists($key, $data)
            ? $data[$key]
            : null;
    }
}
