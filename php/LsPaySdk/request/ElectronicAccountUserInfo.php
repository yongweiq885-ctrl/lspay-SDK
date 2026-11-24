<?php
/**
 * 个人电子账户开户资料（channel_extra.user_info）。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class ElectronicAccountUserInfo
{
    private $certType = '00';
    private $name;
    private $certNo;
    private $certValidityType;
    private $certBeginDate;
    private $certEndDate;
    private $mobileNo;
    private $email;

    public function setCertType($v) { $this->certType = trim((string)$v); return $this; }
    public function setName($v) { $this->name = trim((string)$v); return $this; }
    public function setCertNo($v) { $this->certNo = trim((string)$v); return $this; }
    public function setCertValidityType($v)
    {
        $this->certValidityType = trim((string)$v);
        return $this;
    }
    public function setCertBeginDate($v)
    {
        $this->certBeginDate = trim((string)$v);
        return $this;
    }
    public function setCertEndDate($v)
    {
        $this->certEndDate = trim((string)$v);
        return $this;
    }
    public function setMobileNo($v) { $this->mobileNo = trim((string)$v); return $this; }
    public function setEmail($v) { $this->email = trim((string)$v); return $this; }

    /** @return array<string,string> */
    public function toArray()
    {
        foreach ([
            'name' => $this->name,
            'cert_no' => $this->certNo,
            'cert_validity_type' => $this->certValidityType,
            'cert_begin_date' => $this->certBeginDate,
            'mobile_no' => $this->mobileNo,
            'email' => $this->email,
        ] as $field => $value) {
            if ($value === null || trim((string)$value) === '') {
                throw new LsPayValidationException("channel_extra.user_info.$field 必填");
            }
        }
        if ($this->certType === '') {
            throw new LsPayValidationException(
                'channel_extra.user_info.cert_type 不能为空'
            );
        }
        if (!in_array($this->certValidityType, ['0', '1'], true)) {
            throw new LsPayValidationException(
                'channel_extra.user_info.cert_validity_type 只允许 0 或 1'
            );
        }
        $this->assertDate('cert_begin_date', $this->certBeginDate);
        if ($this->certValidityType === '0') {
            if ($this->certEndDate === null || $this->certEndDate === '') {
                throw new LsPayValidationException(
                    '非长期有效证件必须填写 channel_extra.user_info.cert_end_date'
                );
            }
            $this->assertDate('cert_end_date', $this->certEndDate);
            if ($this->certEndDate < $this->certBeginDate) {
                throw new LsPayValidationException(
                    'channel_extra.user_info.cert_end_date 不能早于 cert_begin_date'
                );
            }
        } elseif ($this->certEndDate !== null && $this->certEndDate !== '') {
            $this->assertDate('cert_end_date', $this->certEndDate);
        }
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new LsPayValidationException(
                'channel_extra.user_info.email 必须是合法邮箱'
            );
        }

        $data = [
            'cert_type' => $this->certType,
            'name' => $this->name,
            'cert_no' => $this->certNo,
            'cert_validity_type' => $this->certValidityType,
            'cert_begin_date' => $this->certBeginDate,
        ];
        if ($this->certEndDate !== null && $this->certEndDate !== '') {
            $data['cert_end_date'] = $this->certEndDate;
        }
        $data['mobile_no'] = $this->mobileNo;
        $data['email'] = $this->email;
        return $data;
    }

    private function assertDate($field, $value)
    {
        if (!preg_match('/^\d{8}$/', (string)$value)) {
            throw new LsPayValidationException(
                "channel_extra.user_info.$field 必须是 yyyyMMdd 格式"
            );
        }
        $date = \DateTimeImmutable::createFromFormat('!Ymd', (string)$value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Ymd') !== (string)$value) {
            throw new LsPayValidationException(
                "channel_extra.user_info.$field 不是有效日期"
            );
        }
    }
}
