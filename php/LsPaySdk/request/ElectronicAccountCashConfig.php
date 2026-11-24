<?php
/**
 * 电子账户提现配置项（channel_extra.cash_config[]）。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class ElectronicAccountCashConfig
{
    private $cashType;
    private $switchState;
    private $outFeeFlag;

    public function setCashType($value)
    {
        $this->cashType = strtoupper(trim((string)$value));
        return $this;
    }

    public function setSwitchState($value)
    {
        $this->switchState = $this->parseInteger('switch_state', $value);
        return $this;
    }

    public function setOutFeeFlag($value)
    {
        $this->outFeeFlag = $this->parseInteger('out_fee_flag', $value);
        return $this;
    }

    /** @return array<string,string|int> */
    public function toArray()
    {
        if (!in_array($this->cashType, ['D0', 'D1', 'DM'], true)) {
            throw new LsPayValidationException(
                'channel_extra.cash_config.cash_type 只允许 D0、D1、DM'
            );
        }
        if (!in_array($this->switchState, [1, 2], true)) {
            throw new LsPayValidationException(
                'channel_extra.cash_config.switch_state 只允许 1 或 2'
            );
        }
        if (!in_array($this->outFeeFlag, [1, 2], true)) {
            throw new LsPayValidationException(
                'channel_extra.cash_config.out_fee_flag 只允许 1 或 2'
            );
        }
        return [
            'cash_type' => $this->cashType,
            'switch_state' => $this->switchState,
            'out_fee_flag' => $this->outFeeFlag,
        ];
    }

    private function parseInteger($field, $value)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new LsPayValidationException(
                "channel_extra.cash_config.$field 必须是整数"
            );
        }
        return (int)$value;
    }
}
