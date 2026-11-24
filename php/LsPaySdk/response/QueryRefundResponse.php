<?php
/**
 * 退款订单查询响应。
 *
 * status=200 表示查询成功；退款是否完成以 data.state=2 为准。
 * 同步响应不执行签名校验。
 */
namespace LsPaySdk\response;

class QueryRefundResponse extends RefundResponse
{
    public function getPayOrderId() { return $this->dataValue('pay_order_id'); }
    public function getMchNo() { return $this->dataValue('mch_no'); }
    public function getAppId() { return $this->dataValue('app_id'); }
    public function getCurrency() { return $this->dataValue('currency'); }
    public function getExtParam() { return $this->dataValue('ext_param'); }
    /** 10 位秒级时间戳。 */
    public function getAddTime() { return $this->integerValue('add_time'); }
    /** 10 位秒级时间戳。 */
    public function getSuccessTime() { return $this->integerValue('success_time'); }
}
