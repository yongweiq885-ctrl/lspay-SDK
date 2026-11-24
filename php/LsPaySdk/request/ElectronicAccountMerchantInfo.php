<?php
/**
 * 企业/个体户电子账户开户资料（channel_extra.mer_info）。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class ElectronicAccountMerchantInfo
{
    private $values = [];

    public function setRegName($v) { return $this->set('reg_name', $v); }
    public function setShortName($v) { return $this->set('short_name', $v); }
    public function setLicenseCode($v) { return $this->set('license_code', $v); }
    public function setLicenseValidityType($v) { return $this->set('license_validity_type', $v); }
    public function setLicenseBeginDate($v) { return $this->set('license_begin_date', $v); }
    public function setLicenseEndDate($v) { return $this->set('license_end_date', $v); }
    public function setRegProvId($v) { return $this->set('reg_prov_id', $v); }
    public function setRegAreaId($v) { return $this->set('reg_area_id', $v); }
    public function setRegDistrictId($v) { return $this->set('reg_district_id', $v); }
    public function setRegDetail($v) { return $this->set('reg_detail', $v); }
    public function setLegalName($v) { return $this->set('legal_name', $v); }
    public function setLegalCertType($v) { return $this->set('legal_cert_type', $v); }
    public function setLegalCertNo($v) { return $this->set('legal_cert_no', $v); }
    public function setLegalCertValidityType($v)
    {
        return $this->set('legal_cert_validity_type', $v);
    }
    public function setLegalCertBeginDate($v) { return $this->set('legal_cert_begin_date', $v); }
    public function setLegalCertEndDate($v) { return $this->set('legal_cert_end_date', $v); }
    public function setContactName($v) { return $this->set('contact_name', $v); }
    public function setContactMobile($v) { return $this->set('contact_mobile', $v); }

    /** @return array<string,string> */
    public function toArray()
    {
        $required = [
            'reg_name',
            'short_name',
            'license_code',
            'license_validity_type',
            'license_begin_date',
            'reg_prov_id',
            'reg_area_id',
            'reg_district_id',
            'reg_detail',
            'legal_name',
            'legal_cert_type',
            'legal_cert_no',
            'legal_cert_validity_type',
            'legal_cert_begin_date',
            'contact_name',
            'contact_mobile',
        ];
        foreach ($required as $field) {
            if (!isset($this->values[$field]) || $this->values[$field] === '') {
                throw new LsPayValidationException(
                    "channel_extra.mer_info.$field 必填"
                );
            }
        }
        $this->validateValidityDates(
            'license',
            $this->values['license_validity_type'],
            $this->values['license_begin_date'],
            isset($this->values['license_end_date'])
                ? $this->values['license_end_date']
                : null
        );
        $this->validateValidityDates(
            'legal_cert',
            $this->values['legal_cert_validity_type'],
            $this->values['legal_cert_begin_date'],
            isset($this->values['legal_cert_end_date'])
                ? $this->values['legal_cert_end_date']
                : null
        );
        foreach (['reg_prov_id', 'reg_area_id', 'reg_district_id'] as $field) {
            if (!preg_match('/^\d{6}$/', $this->values[$field])) {
                throw new LsPayValidationException(
                    "channel_extra.mer_info.$field 必须是 6 位地区编码"
                );
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

    private function validateValidityDates($prefix, $type, $beginDate, $endDate)
    {
        if (!in_array($type, ['0', '1'], true)) {
            throw new LsPayValidationException(
                "channel_extra.mer_info.{$prefix}_validity_type 只允许 0 或 1"
            );
        }
        $this->assertDate("{$prefix}_begin_date", $beginDate);
        if ($type === '0') {
            if ($endDate === null || $endDate === '') {
                throw new LsPayValidationException(
                    "非长期有效证照必须填写 channel_extra.mer_info.{$prefix}_end_date"
                );
            }
            $this->assertDate("{$prefix}_end_date", $endDate);
            if ($endDate < $beginDate) {
                throw new LsPayValidationException(
                    "channel_extra.mer_info.{$prefix}_end_date 不能早于开始日期"
                );
            }
        } elseif ($endDate !== null && $endDate !== '') {
            $this->assertDate("{$prefix}_end_date", $endDate);
        }
    }

    private function assertDate($field, $value)
    {
        if (!preg_match('/^\d{8}$/', (string)$value)) {
            throw new LsPayValidationException(
                "channel_extra.mer_info.$field 必须是 yyyyMMdd 格式"
            );
        }
        $date = \DateTimeImmutable::createFromFormat('!Ymd', (string)$value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Ymd') !== (string)$value) {
            throw new LsPayValidationException(
                "channel_extra.mer_info.$field 不是有效日期"
            );
        }
    }
}
