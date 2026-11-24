<?php
/**
 * 可转余额查询响应。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;

class DelayTransUnconfirmQueryResponse extends LsPayResponse
{
    /** 可转余额，单位：分。 */
    public function getUnconfirmAmount()
    {
        $data = $this->getData();
        if (!is_array($data) || !array_key_exists('unconfirm_amount', $data)) {
            return null;
        }
        $value = $data['unconfirm_amount'];
        return $value === null || $value === '' ? null : (int)$value;
    }

    public function isSuccessful()
    {
        return !$this->isError();
    }
}
