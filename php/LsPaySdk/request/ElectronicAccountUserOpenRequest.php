<?php
/**
 * 电子账户用户开通请求。
 *
 * 端点：/gateway/user/personapply
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\ElectronicAccountRelationTypeEnum;
use LsPaySdk\enums\ElectronicAccountTypeEnum;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class ElectronicAccountUserOpenRequest extends BaseRequest
{
    protected $wayCode = WayCodeEnum::APPLY;
    protected $orderId;
    protected $accountName;
    protected $accountType;
    protected $relationType;
    protected $relationTypeName;
    protected $accountAlias;
    protected $divisionProfit;
    protected $clientIp;
    protected $channelExtra;

    public function getFunctionCode() { return EndpointEnum::ELECTRONIC_ACCOUNT_USER_OPEN; }
    public function getResponseClass()
    {
        return \LsPaySdk\response\ElectronicAccountUserOpenResponse::class;
    }

    public function getWayCode() { return $this->wayCode; }
    public function getOrderId() { return $this->orderId; }
    public function getAccountName() { return $this->accountName; }
    public function getAccountType() { return $this->accountType; }
    public function getRelationType() { return $this->relationType; }
    public function getRelationTypeName() { return $this->relationTypeName; }
    public function getAccountAlias() { return $this->accountAlias; }
    public function getDivisionProfit() { return $this->divisionProfit; }
    public function getClientIp() { return $this->clientIp; }
    public function getChannelExtra() { return $this->channelExtra; }

    public function setWayCode($value)
    {
        $value = trim((string)$value);
        if ($value !== WayCodeEnum::APPLY) {
            throw new LsPayValidationException('way_code 固定为 APPLY');
        }
        $this->wayCode = $value;
        return $this;
    }
    public function setOrderId($v) { $this->orderId = trim((string)$v); return $this; }
    public function setAccountName($v) { $this->accountName = trim((string)$v); return $this; }
    public function setAccountType($v)
    {
        if (filter_var($v, FILTER_VALIDATE_INT) === false) {
            throw new LsPayValidationException('account_type 必须是整数');
        }
        $this->accountType = (int)$v;
        return $this;
    }
    public function setRelationType($v)
    {
        $this->relationType = strtoupper(trim((string)$v));
        return $this;
    }
    public function setRelationTypeName($v)
    {
        $this->relationTypeName = trim((string)$v);
        return $this;
    }
    public function setAccountAlias($v) { $this->accountAlias = trim((string)$v); return $this; }
    public function setDivisionProfit($v)
    {
        $this->divisionProfit = trim((string)$v);
        return $this;
    }
    public function setClientIp($v) { $this->clientIp = trim((string)$v); return $this; }

    public function setChannelExtra($value)
    {
        if ($value instanceof ElectronicAccountOpenChannelExtra) {
            $this->channelExtra = $value->toJson();
            return $this;
        }
        $this->channelExtra = $this->normalizeJsonObject($value);
        return $this;
    }

    public function validate()
    {
        if ($this->wayCode !== WayCodeEnum::APPLY) {
            throw new LsPayValidationException('way_code 固定为 APPLY');
        }
        $this->assertRequiredString('order_id', $this->orderId, 30);
        $this->assertRequiredString('account_name', $this->accountName, 30);
        if (!ElectronicAccountTypeEnum::isValid($this->accountType)) {
            throw new LsPayValidationException('account_type 只允许 1、2、3');
        }
        $this->assertRequiredString('relation_type', $this->relationType, 30);
        if (!ElectronicAccountRelationTypeEnum::isValid($this->relationType)) {
            throw new LsPayValidationException('relation_type 非法');
        }
        $this->assertOptionalString('relation_type_name', $this->relationTypeName, 30);
        if ($this->relationType === ElectronicAccountRelationTypeEnum::CUSTOM
            && ($this->relationTypeName === null || $this->relationTypeName === '')) {
            throw new LsPayValidationException(
                'relation_type=CUSTOM 时 relation_type_name 必填'
            );
        }
        $this->assertRequiredString('account_alias', $this->accountAlias, 30);
        if (!preg_match('/^(?:0(?:\.\d+)?|1(?:\.0+)?)$/', (string)$this->divisionProfit)) {
            throw new LsPayValidationException(
                'division_profit 必须是 0 到 1 之间的十进制比例'
            );
        }
        $this->assertOptionalString('client_ip', $this->clientIp, 32);
        if ($this->clientIp !== null && $this->clientIp !== ''
            && filter_var($this->clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new LsPayValidationException('client_ip 必须是合法 IPv4 地址');
        }
        $this->assertRequiredString('channel_extra', $this->channelExtra, 2048);
        $this->validateChannelExtra();
    }

    private function validateChannelExtra()
    {
        $extra = json_decode((string)$this->channelExtra, true);
        if (!is_array($extra) || json_last_error() !== JSON_ERROR_NONE) {
            throw new LsPayValidationException('channel_extra 必须是 JSON 对象字符串');
        }
        if ($this->accountType === ElectronicAccountTypeEnum::PERSONAL) {
            if (!isset($extra['user_info']) || !is_array($extra['user_info'])) {
                throw new LsPayValidationException(
                    'account_type=1 时 channel_extra.user_info 必填'
                );
            }
            $this->validateUserInfo($extra['user_info']);
            return;
        }
        if (!isset($extra['mer_info']) || !is_array($extra['mer_info'])) {
            throw new LsPayValidationException(
                'account_type=2 或 3 时 channel_extra.mer_info 必填'
            );
        }
        $this->validateMerchantInfo($extra['mer_info']);
    }

    private function validateUserInfo(array $info)
    {
        $builder = new ElectronicAccountUserInfo();
        $setters = [
            'cert_type' => 'setCertType',
            'name' => 'setName',
            'cert_no' => 'setCertNo',
            'cert_validity_type' => 'setCertValidityType',
            'cert_begin_date' => 'setCertBeginDate',
            'cert_end_date' => 'setCertEndDate',
            'mobile_no' => 'setMobileNo',
            'email' => 'setEmail',
        ];
        $this->applyNestedSetters($builder, $info, $setters, 'user_info');
        $builder->toArray();
    }

    private function validateMerchantInfo(array $info)
    {
        $builder = new ElectronicAccountMerchantInfo();
        $setters = [
            'reg_name' => 'setRegName',
            'short_name' => 'setShortName',
            'license_code' => 'setLicenseCode',
            'license_validity_type' => 'setLicenseValidityType',
            'license_begin_date' => 'setLicenseBeginDate',
            'license_end_date' => 'setLicenseEndDate',
            'reg_prov_id' => 'setRegProvId',
            'reg_area_id' => 'setRegAreaId',
            'reg_district_id' => 'setRegDistrictId',
            'reg_detail' => 'setRegDetail',
            'legal_name' => 'setLegalName',
            'legal_cert_type' => 'setLegalCertType',
            'legal_cert_no' => 'setLegalCertNo',
            'legal_cert_validity_type' => 'setLegalCertValidityType',
            'legal_cert_begin_date' => 'setLegalCertBeginDate',
            'legal_cert_end_date' => 'setLegalCertEndDate',
            'contact_name' => 'setContactName',
            'contact_mobile' => 'setContactMobile',
        ];
        $this->applyNestedSetters($builder, $info, $setters, 'mer_info');
        $builder->toArray();
    }

    private function applyNestedSetters($builder, array $values, array $setters, $prefix)
    {
        foreach ($setters as $field => $setter) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            if ($values[$field] !== null
                && !is_string($values[$field])
                && !is_numeric($values[$field])) {
                throw new LsPayValidationException(
                    "channel_extra.$prefix.$field 必须是字符串"
                );
            }
            if ($values[$field] !== null) {
                $builder->$setter($values[$field]);
            }
        }
    }

    private function normalizeJsonObject($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            $decoded = (array)$value;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw new LsPayValidationException(
                    'channel_extra 必须是合法 JSON 对象字符串'
                );
            }
        } else {
            throw new LsPayValidationException(
                'channel_extra 只支持关联数组、对象或 JSON 字符串'
            );
        }
        if ($decoded !== [] && array_keys($decoded) === range(0, count($decoded) - 1)) {
            throw new LsPayValidationException(
                'channel_extra 必须是 JSON 对象，不能是数组列表'
            );
        }
        $json = json_encode(
            (object)$decoded,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            throw new LsPayValidationException(
                'channel_extra JSON 编码失败: ' . json_last_error_msg()
            );
        }
        return $json;
    }

    private function assertRequiredString($field, $value, $maxLength)
    {
        if ($value === null || trim((string)$value) === '') {
            throw new LsPayValidationException("$field 必填");
        }
        $this->assertOptionalString($field, $value, $maxLength);
    }

    private function assertOptionalString($field, $value, $maxLength)
    {
        if ($value === null || $value === '') {
            return;
        }
        if (!is_string($value) && !is_numeric($value)) {
            throw new LsPayValidationException("$field 必须是字符串");
        }
        $length = function_exists('mb_strlen')
            ? mb_strlen((string)$value, 'UTF-8')
            : strlen((string)$value);
        if ($length > $maxLength) {
            throw new LsPayValidationException("$field 长度不能超过 $maxLength");
        }
    }
}
