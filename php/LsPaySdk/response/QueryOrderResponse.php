<?php
/**
 * 订单查询响应。
 *
 * status=200 表示查询接口成功；订单是否支付成功以 data.state=2 为准。
 * 同步响应不执行签名校验。
 */
namespace LsPaySdk\response;

use LsPaySdk\core\LsPayResponse;
use LsPaySdk\enums\OrderStateEnum;

class QueryOrderResponse extends LsPayResponse
{
    public function getPayOrderId() { return $this->dataValue('pay_order_id'); }
    public function getMchNo() { return $this->dataValue('mch_no'); }

    public function getAppId()
    {
        return $this->dataValueAny(['app_id', 'app_Id']);
    }

    public function getMchOrderNo() { return $this->dataValue('mch_order_no'); }
    public function getWayCode() { return $this->dataValue('way_code'); }
    public function getCurrency() { return $this->dataValue('currency'); }
    public function getClientIp() { return $this->dataValue('client_ip'); }
    public function getSubject() { return $this->dataValue('subject'); }
    public function getBody() { return $this->dataValue('body'); }
    public function getChannelOrderNo() { return $this->dataValue('channel_order_no'); }
    public function getErrCode() { return $this->dataValue('err_code'); }
    public function getErrMsg() { return $this->dataValue('err_msg'); }
    public function getExtParam() { return $this->dataValue('ext_param'); }
    public function getFundFreezeStatus() { return $this->dataValue('fund_freeze_stat'); }
    public function getFreezeTime() { return $this->dataValue('freeze_time'); }
    public function getUnfreezeAmount() { return $this->dataValue('unfreeze_amt'); }
    public function getUnfreezeTime() { return $this->dataValue('unfreeze_time'); }

    public function getAmount() { return $this->integerValue('amount'); }
    public function getState() { return $this->integerValue('state'); }
    public function getOrderState() { return $this->getState(); }
    /** 10 位秒级时间戳。 */
    public function getAddTime() { return $this->integerValue('add_time'); }
    /** 10 位秒级时间戳。 */
    public function getSuccessTime() { return $this->integerValue('success_time'); }
    public function getDivisionMode() { return $this->integerValue('division_mode'); }
    public function getDivisionState() { return $this->integerValue('division_state'); }
    public function getUnconfirmAmount() { return $this->integerValue('unconfirm_amount'); }
    public function getConfirmedAmount() { return $this->integerValue('confirmed_amount'); }

    public function getMchFeeAmount()
    {
        $value = $this->dataValue('mch_fee_amount');
        if ($value === null) {
            $data = $this->getData();
            if (is_array($data)) {
                foreach ($data as $key => $candidate) {
                    if (trim((string)$key) === 'mch_fee_amount') {
                        $value = $candidate;
                        break;
                    }
                }
            }
        }
        return $value === null ? null : (int)$value;
    }

    public function isPaid()
    {
        return !$this->isError() && $this->getState() === OrderStateEnum::PAID;
    }

    public function isFundFrozen()
    {
        return strtoupper((string)$this->getFundFreezeStatus()) === 'FREEZE';
    }

    private function integerValue($key)
    {
        $value = $this->dataValue($key);
        return $value === null || $value === '' ? null : (int)$value;
    }

    private function dataValue($key)
    {
        $data = $this->getData();
        return is_array($data) && array_key_exists($key, $data)
            ? $data[$key]
            : null;
    }

    private function dataValueAny(array $keys)
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
