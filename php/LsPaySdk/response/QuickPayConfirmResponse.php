<?php
/**
 * 快捷支付确认响应。
 */
namespace LsPaySdk\response;

class QuickPayConfirmResponse extends QuickPayResponse
{
    public function getErrMsg()
    {
        return $this->quickPayValue('err_msg');
    }
}
