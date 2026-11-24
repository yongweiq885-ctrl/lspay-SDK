<?php
/**
 * 余额支付 channel_extra 构造器。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class BalancePayChannelExtra extends DivisionChannelExtra
{
    public function setTransOut($transOut)
    {
        if (!is_string($transOut) && !is_numeric($transOut)) {
            throw new LsPayValidationException('channel_extra.trans_out 必须是字符串');
        }
        $transOut = trim((string)$transOut);
        if ($transOut === '') {
            throw new LsPayValidationException('channel_extra.trans_out 必填');
        }
        return $this->set('trans_out', $transOut);
    }
}
