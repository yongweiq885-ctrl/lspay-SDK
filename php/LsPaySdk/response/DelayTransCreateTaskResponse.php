<?php
/**
 * 延时分账订单转账响应。
 *
 * 顶层 getStatus() 返回公共响应状态；getTaskStatus() 返回 data.status。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;
use LsPaySdk\enums\DelayTransTaskStatusEnum;

class DelayTransCreateTaskResponse extends LsPayResponse
{
    public function getTaskId() { return $this->stringValue('task_id'); }
    public function getOrderNo() { return $this->stringValue('order_no'); }
    public function getAccountNo() { return $this->stringValue('account_no'); }
    public function getAccountName() { return $this->stringValue('account_name'); }
    public function getAmount() { return $this->integerValue('amount'); }
    public function getTaskStatus() { return $this->stringValue('status'); }
    public function getSelectedOrders() { return $this->integerValue('selected_orders'); }
    public function getFirstOrderTime() { return $this->stringValue('first_order_time'); }
    public function getLastOrderTime() { return $this->stringValue('last_order_time'); }

    public function isCreated()
    {
        return !$this->isError()
            && $this->getTaskStatus() === DelayTransTaskStatusEnum::PENDING;
    }

    private function dataValue($key)
    {
        $data = $this->getData();
        return is_array($data) && array_key_exists($key, $data)
            ? $data[$key]
            : null;
    }

    private function stringValue($key)
    {
        $value = $this->dataValue($key);
        return $value === null ? null : (string)$value;
    }

    private function integerValue($key)
    {
        $value = $this->dataValue($key);
        return $value === null || $value === '' ? null : (int)$value;
    }
}
