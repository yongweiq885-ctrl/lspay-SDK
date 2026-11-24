<?php
/**
 * 快捷支付绑卡确认 channel_extra 构造器。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class QuickPayBindConfirmChannelExtra
{
    private $mchUserId;
    private $certType = '00';
    private $cardId;
    private $cardName;
    private $certId;
    private $cardMp;
    private $dcType;

    public function setMchUserId($value) { $this->mchUserId = trim((string)$value); return $this; }
    public function setCertType($value) { $this->certType = trim((string)$value); return $this; }
    public function setCardId($value) { $this->cardId = trim((string)$value); return $this; }
    public function setCardName($value) { $this->cardName = trim((string)$value); return $this; }
    public function setCertId($value) { $this->certId = trim((string)$value); return $this; }
    public function setCardMp($value) { $this->cardMp = trim((string)$value); return $this; }
    public function setDcType($value)
    {
        $this->dcType = strtoupper(trim((string)$value));
        return $this;
    }

    /** @return array<string,string> */
    public function toArray()
    {
        foreach ([
            'mch_user_id' => $this->mchUserId,
            'card_id' => $this->cardId,
            'card_name' => $this->cardName,
            'cert_id' => $this->certId,
            'card_mp' => $this->cardMp,
            'dc_type' => $this->dcType,
        ] as $field => $value) {
            if ($value === null || trim((string)$value) === '') {
                throw new LsPayValidationException("channel_extra.$field 必填");
            }
        }
        if ($this->certType === '') {
            throw new LsPayValidationException('channel_extra.cert_type 不能为空');
        }
        if (!in_array($this->dcType, ['C', 'D', 'S', 'U'], true)) {
            throw new LsPayValidationException(
                'channel_extra.dc_type 只允许 C、D、S、U'
            );
        }
        return [
            'mch_user_id' => $this->mchUserId,
            'cert_type' => $this->certType,
            'card_id' => $this->cardId,
            'card_name' => $this->cardName,
            'cert_id' => $this->certId,
            'card_mp' => $this->cardMp,
            'dc_type' => $this->dcType,
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
