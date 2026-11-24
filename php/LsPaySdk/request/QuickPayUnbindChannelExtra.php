<?php
/**
 * 快捷支付解绑 channel_extra 构造器。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class QuickPayUnbindChannelExtra
{
    private $mchUserId;
    private $channelCardId;

    public function setMchUserId($value)
    {
        $this->mchUserId = trim((string)$value);
        return $this;
    }

    public function setChannelCardId($value)
    {
        $this->channelCardId = trim((string)$value);
        return $this;
    }

    /** @return array<string,string> */
    public function toArray()
    {
        if ($this->mchUserId === null || $this->mchUserId === '') {
            throw new LsPayValidationException('channel_extra.mch_user_id 必填');
        }
        if ($this->channelCardId === null || $this->channelCardId === '') {
            throw new LsPayValidationException('channel_extra.channel_card_id 必填');
        }
        return [
            'mch_user_id' => $this->mchUserId,
            'channel_card_id' => $this->channelCardId,
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
