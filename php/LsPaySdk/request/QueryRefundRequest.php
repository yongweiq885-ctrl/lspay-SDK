<?php
/**
 * 退款查询请求
 *   端点: /gateway/refund/query
 *
 * 商户退款单号 / 平台退款单号 至少传一个。
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class QueryRefundRequest extends BaseRequest
{
    /** 商户退款单号 */
    protected $mchRefundNo;
    /** 平台退款单号 */
    protected $orderRefundId;

    public function getFunctionCode() { return EndpointEnum::QUERY_REFUND; }
    public function getResponseClass() { return \LsPaySdk\response\QueryRefundResponse::class; }

    public function getMchRefundNo()   { return $this->mchRefundNo; }
    public function getOrderRefundId() { return $this->orderRefundId; }

    public function setMchRefundNo($v)
    {
        $this->mchRefundNo = trim((string)$v);
        return $this;
    }

    public function setOrderRefundId($v)
    {
        $this->orderRefundId = trim((string)$v);
        return $this;
    }

    /** @deprecated 使用 getOrderRefundId() */
    public function getRefundOrderId() { return $this->getOrderRefundId(); }

    /** @deprecated 使用 setOrderRefundId() */
    public function setRefundOrderId($v) { return $this->setOrderRefundId($v); }

    /** @deprecated 使用 getOrderRefundId() */
    public function getRefundOrderNo() { return $this->getOrderRefundId(); }

    /** @deprecated 使用 setOrderRefundId() */
    public function setRefundOrderNo($v) { return $this->setOrderRefundId($v); }

    public function validate()
    {
        if (($this->mchRefundNo === null || $this->mchRefundNo === '')
            && ($this->orderRefundId === null || $this->orderRefundId === '')) {
            throw new LsPayValidationException(
                'mch_refund_no 与 order_refund_id 至少填写一个'
            );
        }
        $this->assertMaxLength('mch_refund_no', $this->mchRefundNo, 30);
        $this->assertMaxLength('order_refund_id', $this->orderRefundId, 30);
    }

    private function assertMaxLength($field, $value, $maxLength)
    {
        if ($value === null || $value === '') {
            return;
        }
        $length = function_exists('mb_strlen')
            ? mb_strlen((string)$value, 'UTF-8')
            : strlen((string)$value);
        if ($length > $maxLength) {
            throw new LsPayValidationException("$field 长度不能超过 $maxLength");
        }
    }
}
