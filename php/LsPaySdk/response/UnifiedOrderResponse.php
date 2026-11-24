<?php
/**
 * 统一下单响应。
 *
 * status=200 仅表示接口请求成功；是否已经支付必须根据 order_state 判断。
 * 同步响应不验签。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;
use LsPaySdk\enums\OrderStateEnum;

class UnifiedOrderResponse extends LsPayResponse
{
    public function getPayOrderId()
    {
        return $this->dataValue('pay_order_id');
    }

    public function getMchOrderNo()
    {
        return $this->dataValue('mch_order_no');
    }

    public function getAmount()
    {
        $value = $this->dataValue('amount');
        return $value === null ? null : (int)$value;
    }

    public function getOrderState()
    {
        $value = $this->dataValue('order_state');
        return $value === null ? null : (int)$value;
    }

    /**
     * 返回平台按 channel_extra.pay_data_type 生成的支付数据。
     * SDK不擅自解析或转换其内容。
     */
    public function getPayInfo()
    {
        return $this->dataValue('pay_info');
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

    /** 接口是否受理成功；不代表用户已经完成付款。 */
    public function isAccepted()
    {
        return !$this->isError() && strtoupper((string)$this->getTransStatus()) === 'S';
    }

    /** order_state=2 才表示支付成功。 */
    public function isPaid()
    {
        return $this->getOrderState() === OrderStateEnum::PAID;
    }

    private function dataValue($key)
    {
        $data = $this->getData();
        return is_array($data) && array_key_exists($key, $data)
            ? $data[$key]
            : null;
    }
}
