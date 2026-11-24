<?php
/**
 * 电子账户业务变更 channel_extra 构造器。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class ElectronicAccountBusinessChangeChannelExtra
{
    private $cardInfo;
    private $settleConfig;
    private $cashConfig = [];

    public function setCardInfo(ElectronicAccountModifiedCard $value)
    {
        $this->cardInfo = $value->toArray();
        return $this;
    }

    public function setSettleConfig(ElectronicAccountSettleConfig $value)
    {
        $this->settleConfig = $value->toArray();
        return $this;
    }

    public function addCashConfig(ElectronicAccountCashConfig $value)
    {
        $this->cashConfig[] = $value->toArray();
        return $this;
    }

    /** @return array<string,mixed> */
    public function toArray()
    {
        if ($this->cardInfo === null) {
            throw new LsPayValidationException('channel_extra.card_info 必填');
        }
        if ($this->settleConfig === null) {
            throw new LsPayValidationException('channel_extra.settle_config 必填');
        }
        if (!$this->cashConfig) {
            throw new LsPayValidationException(
                'channel_extra.cash_config 必填且至少包含一项'
            );
        }
        return [
            'card_info' => $this->cardInfo,
            'settle_config' => $this->settleConfig,
            'cash_config' => $this->cashConfig,
        ];
    }

    public function toJson()
    {
        $json = json_encode(
            (object)$this->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            throw new LsPayValidationException(
                'channel_extra JSON 编码失败: ' . json_last_error_msg()
            );
        }
        return $json;
    }
}
