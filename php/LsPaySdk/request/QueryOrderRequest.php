<?php
/**
 * 订单查询请求
 *   端点: /gateway/query
 *
 * 商户订单号 / 平台支付订单号 至少传一个。
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class QueryOrderRequest extends BaseRequest
{
    /** 商户订单号 */
    protected $mchOrderNo;
    /** 平台支付订单号 */
    protected $payOrderId;

    public function getFunctionCode() { return EndpointEnum::QUERY_ORDER; }
    public function getResponseClass() { return \LsPaySdk\response\QueryOrderResponse::class; }

    public function getMchOrderNo() { return $this->mchOrderNo; }
    public function getPayOrderId() { return $this->payOrderId; }

    public function setMchOrderNo($v)
    {
        $this->mchOrderNo = trim((string)$v);
        return $this;
    }

    public function setPayOrderId($v)
    {
        $this->payOrderId = trim((string)$v);
        return $this;
    }

    /** @deprecated 使用 getPayOrderId() */
    public function getPayOrderNo() { return $this->getPayOrderId(); }

    /** @deprecated 使用 setPayOrderId() */
    public function setPayOrderNo($v) { return $this->setPayOrderId($v); }

    public function validate()
    {
        if (($this->mchOrderNo === null || $this->mchOrderNo === '')
            && ($this->payOrderId === null || $this->payOrderId === '')) {
            throw new LsPayValidationException(
                'mch_order_no 与 pay_order_id 至少填写一个'
            );
        }
        $this->assertMaxLength('mch_order_no', $this->mchOrderNo, 30);
        $this->assertMaxLength('pay_order_id', $this->payOrderId, 30);
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
