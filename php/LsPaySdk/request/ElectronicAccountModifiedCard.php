<?php
/**
 * 电子账户业务变更结算卡（channel_extra.card_info）。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class ElectronicAccountModifiedCard
{
    private $cardType;
    private $cardName;
    private $cardNo;

    public function setCardType($v)
    {
        $this->cardType = trim((string)$v);
        return $this;
    }

    public function setCardName($v)
    {
        $this->cardName = trim((string)$v);
        return $this;
    }

    public function setCardNo($v)
    {
        $this->cardNo = trim((string)$v);
        return $this;
    }

    /** @return array<string,string> */
    public function toArray()
    {
        if (!in_array($this->cardType, ['0', '1', '2'], true)) {
            throw new LsPayValidationException(
                'channel_extra.card_info.card_type 只允许 0、1、2'
            );
        }
        if ($this->cardName === null || $this->cardName === '') {
            throw new LsPayValidationException(
                'channel_extra.card_info.card_name 必填'
            );
        }
        if ($this->cardNo === null || $this->cardNo === '') {
            throw new LsPayValidationException(
                'channel_extra.card_info.card_no 必填'
            );
        }
        return [
            'card_type' => $this->cardType,
            'card_name' => $this->cardName,
            'card_no' => $this->cardNo,
        ];
    }
}
