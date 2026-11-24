<?php
/**
 * 申请退款响应。
 *
 * status=200 仅表示退款申请接口调用成功；退款完成以 data.state=2 为准。
 * 网关示例虽返回顶层 sign，同步响应按协议不执行验签。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;
use LsPaySdk\enums\RefundStateEnum;

class RefundResponse extends LsPayResponse
{
    public function getRefundOrderId()
    {
        return $this->dataValueAny([
            'refund_order_id',
            'order_refund_id',
            'order_refund_Id',
        ]);
    }

    public function getOrderRefundId() { return $this->getRefundOrderId(); }
    public function getMchRefundNo() { return $this->dataValue('mch_refund_no'); }
    public function getChannelOrderNo()
    {
        return $this->dataValueAny(['channel_order_no', 'channel_rrder_no']);
    }
    public function getErrCode() { return $this->dataValue('err_code'); }
    public function getErrMsg() { return $this->dataValue('err_msg'); }
    public function getState() { return $this->integerValue('state'); }
    public function getPayAmount() { return $this->integerValue('pay_amount'); }
    public function getRefundAmount() { return $this->integerValue('refund_amount'); }

    /** 返回同步响应携带的原始 sign；SDK 不对其执行验签。 */
    public function getResponseSign()
    {
        $raw = $this->getRaw();
        return array_key_exists('sign', $raw) ? $raw['sign'] : null;
    }

    /** 接口已接受退款申请，不代表退款已经完成。 */
    public function isAccepted()
    {
        return !$this->isError();
    }

    public function isRefunded()
    {
        return !$this->isError()
            && $this->getState() === RefundStateEnum::SUCCEEDED;
    }

    protected function integerValue($key)
    {
        $value = $this->dataValue($key);
        return $value === null || $value === '' ? null : (int)$value;
    }

    protected function dataValue($key)
    {
        $data = $this->getData();
        return is_array($data) && array_key_exists($key, $data)
            ? $data[$key]
            : null;
    }

    protected function dataValueAny(array $keys)
    {
        foreach ($keys as $key) {
            $value = $this->dataValue($key);
            if ($value !== null) {
                return $value;
            }
        }
        return null;
    }
}
