<?php
/**
 * 快捷支付绑卡申请 channel_extra 构造器。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class QuickPayBindChannelExtra
{
    private $mchUserId;
    private $certType = '00';
    private $cardId;
    private $cardName;
    private $certId;
    private $certValidityType;
    private $certBeginDate;
    private $certEndDate;
    private $cardMp;
    private $vipCode;
    private $expiration;
    private $dcType;

    public function setMchUserId($value) { $this->mchUserId = trim((string)$value); return $this; }
    public function setCertType($value) { $this->certType = trim((string)$value); return $this; }
    public function setCardId($value) { $this->cardId = trim((string)$value); return $this; }
    public function setCardName($value) { $this->cardName = trim((string)$value); return $this; }
    public function setCertId($value) { $this->certId = trim((string)$value); return $this; }
    public function setCertValidityType($value)
    {
        $this->certValidityType = trim((string)$value);
        return $this;
    }
    public function setCertBeginDate($value)
    {
        $this->certBeginDate = trim((string)$value);
        return $this;
    }
    public function setCertEndDate($value)
    {
        $this->certEndDate = trim((string)$value);
        return $this;
    }
    public function setCardMp($value) { $this->cardMp = trim((string)$value); return $this; }
    public function setVipCode($value) { $this->vipCode = trim((string)$value); return $this; }
    public function setExpiration($value)
    {
        $this->expiration = trim((string)$value);
        return $this;
    }
    public function setDcType($value)
    {
        $this->dcType = strtoupper(trim((string)$value));
        return $this;
    }

    /** @return array<string,string> */
    public function toArray()
    {
        $this->validate();
        $data = [
            'mch_user_id' => $this->mchUserId,
            'cert_type' => $this->certType,
            'card_id' => $this->cardId,
            'card_name' => $this->cardName,
            'cert_id' => $this->certId,
            'cert_validity_type' => $this->certValidityType,
            'cert_begin_date' => $this->certBeginDate,
        ];
        if ($this->certEndDate !== null && $this->certEndDate !== '') {
            $data['cert_end_date'] = $this->certEndDate;
        }
        $data['card_mp'] = $this->cardMp;
        if ($this->vipCode !== null && $this->vipCode !== '') {
            $data['vip_code'] = $this->vipCode;
        }
        if ($this->expiration !== null && $this->expiration !== '') {
            $data['expiration'] = $this->expiration;
        }
        $data['dc_type'] = $this->dcType;
        return $data;
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

    private function validate()
    {
        foreach ([
            'mch_user_id' => $this->mchUserId,
            'card_id' => $this->cardId,
            'card_name' => $this->cardName,
            'cert_id' => $this->certId,
            'cert_validity_type' => $this->certValidityType,
            'cert_begin_date' => $this->certBeginDate,
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
        if (!in_array($this->certValidityType, ['0', '1'], true)) {
            throw new LsPayValidationException(
                'channel_extra.cert_validity_type 只允许 0 或 1'
            );
        }
        $this->assertDate('cert_begin_date', $this->certBeginDate);
        if ($this->certValidityType === '0') {
            if ($this->certEndDate === null || $this->certEndDate === '') {
                throw new LsPayValidationException(
                    '非长期有效证件必须填写 channel_extra.cert_end_date'
                );
            }
            $this->assertDate('cert_end_date', $this->certEndDate);
            if ($this->certEndDate < $this->certBeginDate) {
                throw new LsPayValidationException(
                    'channel_extra.cert_end_date 不能早于 cert_begin_date'
                );
            }
        } elseif ($this->certEndDate !== null && $this->certEndDate !== '') {
            $this->assertDate('cert_end_date', $this->certEndDate);
        }
        if (!in_array($this->dcType, ['C', 'D', 'S', 'U'], true)) {
            throw new LsPayValidationException(
                'channel_extra.dc_type 只允许 C、D、S、U'
            );
        }
        if ($this->expiration !== null && $this->expiration !== '') {
            if (!preg_match('/^(0[1-9]|1[0-2])\d{2}$/', $this->expiration)) {
                throw new LsPayValidationException(
                    'channel_extra.expiration 必须是 MMYY 格式'
                );
            }
        }
    }

    private function assertDate($field, $value)
    {
        if (!preg_match('/^\d{8}$/', (string)$value)) {
            throw new LsPayValidationException(
                "channel_extra.$field 必须是 yyyyMMdd 格式"
            );
        }
        $date = \DateTimeImmutable::createFromFormat('!Ymd', (string)$value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Ymd') !== (string)$value) {
            throw new LsPayValidationException(
                "channel_extra.$field 不是有效日期"
            );
        }
    }
}
