<?php
/**
 * 快捷支付申请请求。
 *
 * 复用统一下单端点 /gateway/unifiedorder，并固定 way_code=BANK_FAST。
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class QuickPayApplyRequest extends UnifiedOrderRequest
{
    public function __construct()
    {
        $this->wayCode = WayCodeEnum::BANK_FAST;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\QuickPayApplyResponse::class;
    }

    public function setWayCode($value)
    {
        $value = trim((string)$value);
        if ($value !== WayCodeEnum::BANK_FAST) {
            throw new LsPayValidationException('way_code 固定为 BANK_FAST');
        }
        $this->wayCode = $value;
        return $this;
    }
}
