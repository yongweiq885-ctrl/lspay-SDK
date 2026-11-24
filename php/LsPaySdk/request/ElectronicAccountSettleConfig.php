<?php
/**
 * 电子账户结算周期配置（channel_extra.settle_config）。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class ElectronicAccountSettleConfig
{
    private $settleCycle;
    private $settleStatus;

    public function setSettleCycle($value)
    {
        $this->settleCycle = strtoupper(trim((string)$value));
        return $this;
    }

    public function setSettleStatus($value)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new LsPayValidationException('settle_status 必须是整数');
        }
        $this->settleStatus = (int)$value;
        return $this;
    }

    /** @return array<string,string|int> */
    public function toArray()
    {
        if (!in_array($this->settleCycle, ['T1', 'D1'], true)) {
            throw new LsPayValidationException(
                'channel_extra.settle_config.settle_cycle 只允许 T1 或 D1'
            );
        }
        if (!is_int($this->settleStatus)) {
            throw new LsPayValidationException(
                'channel_extra.settle_config.settle_status 必填且必须是整数'
            );
        }
        return [
            'settle_cycle' => $this->settleCycle,
            'settle_status' => $this->settleStatus,
        ];
    }
}
