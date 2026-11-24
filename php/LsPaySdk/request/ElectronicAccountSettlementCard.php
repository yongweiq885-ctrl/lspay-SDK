<?php
/**
 * 电子账户结算卡配置（channel_extra.card_info）。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class ElectronicAccountSettlementCard
{
    private $values = [];

    public function setCardType($v) { return $this->set('card_type', $v); }
    public function setCardName($v) { return $this->set('card_name', $v); }
    public function setCardNo($v) { return $this->set('card_no', $v); }
    public function setProvId($v) { return $this->set('prov_id', $v); }
    public function setAreaId($v) { return $this->set('area_id', $v); }
    public function setBankCode($v) { return $this->set('bank_code', $v); }
    public function setBranchCode($v) { return $this->set('branch_code', $v); }
    public function setBranchName($v) { return $this->set('branch_name', $v); }
    public function setCertType($v) { return $this->set('cert_type', $v); }
    public function setCertNo($v) { return $this->set('cert_no', $v); }
    public function setCertValidityType($v) { return $this->set('cert_validity_type', $v); }
    public function setCertBeginDate($v) { return $this->set('cert_begin_date', $v); }
    public function setCertEndDate($v) { return $this->set('cert_end_date', $v); }
    public function setMobile($v) { return $this->set('mp', $v); }

    /** @return array<string,string> */
    public function toArray()
    {
        foreach (['card_type', 'card_name', 'card_no', 'prov_id', 'area_id', 'mp'] as $field) {
            if (!isset($this->values[$field]) || $this->values[$field] === '') {
                throw new LsPayValidationException(
                    "channel_extra.card_info.$field 必填"
                );
            }
        }
        $cardType = $this->values['card_type'];
        if (!in_array($cardType, ['0', '1', '2'], true)) {
            throw new LsPayValidationException(
                'channel_extra.card_info.card_type 只允许 0、1、2'
            );
        }
        foreach (['prov_id', 'area_id'] as $field) {
            if (!preg_match('/^\d{6}$/', $this->values[$field])) {
                throw new LsPayValidationException(
                    "channel_extra.card_info.$field 必须是 6 位地区编码"
                );
            }
        }
        if ($cardType === '0') {
            if (!isset($this->values['bank_code'])) {
                throw new LsPayValidationException(
                    '对公结算卡必须填写 channel_extra.card_info.bank_code'
                );
            }
            if (!isset($this->values['branch_code'])
                && !isset($this->values['branch_name'])) {
                throw new LsPayValidationException(
                    '对公结算卡的 branch_code 与 branch_name 至少填写一个'
                );
            }
        } else {
            foreach ([
                'cert_type',
                'cert_no',
                'cert_validity_type',
                'cert_begin_date',
            ] as $field) {
                if (!isset($this->values[$field]) || $this->values[$field] === '') {
                    throw new LsPayValidationException(
                        "对私结算卡必须填写 channel_extra.card_info.$field"
                    );
                }
            }
            if (!in_array($this->values['cert_validity_type'], ['0', '1'], true)) {
                throw new LsPayValidationException(
                    'channel_extra.card_info.cert_validity_type 只允许 0 或 1'
                );
            }
            $this->assertDate('cert_begin_date', $this->values['cert_begin_date']);
            if ($this->values['cert_validity_type'] === '0') {
                if (!isset($this->values['cert_end_date'])) {
                    throw new LsPayValidationException(
                        '非长期有效证件必须填写 channel_extra.card_info.cert_end_date'
                    );
                }
                $this->assertDate('cert_end_date', $this->values['cert_end_date']);
                if ($this->values['cert_end_date'] < $this->values['cert_begin_date']) {
                    throw new LsPayValidationException(
                        'channel_extra.card_info.cert_end_date 不能早于 cert_begin_date'
                    );
                }
            }
        }
        return $this->values;
    }

    private function set($field, $value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            unset($this->values[$field]);
        } else {
            $this->values[$field] = $value;
        }
        return $this;
    }

    private function assertDate($field, $value)
    {
        if (!preg_match('/^\d{8}$/', (string)$value)) {
            throw new LsPayValidationException(
                "channel_extra.card_info.$field 必须是 yyyyMMdd 格式"
            );
        }
        $date = \DateTimeImmutable::createFromFormat('!Ymd', (string)$value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Ymd') !== (string)$value) {
            throw new LsPayValidationException(
                "channel_extra.card_info.$field 不是有效日期"
            );
        }
    }
}
