<?php
/**
 * 快捷支付绑卡申请响应。
 *
 * 兼容公共 status/msg/data 外层，以及官方示例中的直接业务字段格式。
 */
namespace LsPaySdk\response;

class QuickPayBindApplyResponse extends QuickPayResponse
{
    public function getTransId() { return $this->quickPayValue('trans_id'); }
    public function getOrderId() { return $this->quickPayValue('order_id'); }
    public function getRespDesc() { return $this->quickPayValue('resp_desc'); }
}
