<?php
/**
 * 余额支付请求。
 *
 * 与统一下单共用 /gateway/unifiedorder 端点。
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class BalancePayRequest extends UnifiedOrderRequest
{
    public function __construct()
    {
        $this->wayCode = WayCodeEnum::BALANCE_PAY;
        $this->divisionMode = 1;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\BalancePayResponse::class;
    }

    public function validate()
    {
        parent::validate();

        if ($this->wayCode !== WayCodeEnum::BALANCE_PAY) {
            throw new LsPayValidationException(
                'way_code 固定为 ' . WayCodeEnum::BALANCE_PAY
            );
        }
        if ($this->divisionMode !== 1) {
            throw new LsPayValidationException('division_mode 固定为 1');
        }

        $extra = json_decode((string)$this->channelExtra, true);
        if (!is_array($extra)) {
            throw new LsPayValidationException('channel_extra 必填且必须是 JSON 对象');
        }
        if (!isset($extra['trans_out'])
            || (!is_string($extra['trans_out']) && !is_numeric($extra['trans_out']))
            || trim((string)$extra['trans_out']) === '') {
            throw new LsPayValidationException(
                'channel_extra.trans_out 必填且必须是字符串'
            );
        }

        $total = 0;
        foreach ($extra['div_list'] as $index => $receiver) {
            if (!array_key_exists('div_amt', $receiver)
                || !is_int($receiver['div_amt'])
                || $receiver['div_amt'] <= 0) {
                throw new LsPayValidationException(
                    "channel_extra.div_list[$index].div_amt 必填且必须是大于 0 的整数（单位：分）"
                );
            }
            $total += $receiver['div_amt'];
        }
        if ($total > $this->amount) {
            throw new LsPayValidationException(
                'channel_extra.div_list 的 div_amt 总和不能超过 amount'
            );
        }
    }
}
