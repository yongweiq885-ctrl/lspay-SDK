<?php
/**
 * 快捷支付解绑响应。
 */
namespace LsPaySdk\response;

class QuickPayUnbindResponse extends QuickPayResponse
{
    public function getOrderId()
    {
        return $this->quickPayValue('order_id');
    }

    public function getRespDesc()
    {
        return $this->quickPayValue('resp_desc');
    }
}
