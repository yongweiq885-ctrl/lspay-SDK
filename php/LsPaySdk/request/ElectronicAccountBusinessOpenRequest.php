<?php
/**
 * 电子账户业务开通请求。
 *
 * 端点：/gateway/user/personconfirm
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class ElectronicAccountBusinessOpenRequest extends BaseRequest
{
    const WAY_CODE = 'APPLY';

    protected $wayCode = self::WAY_CODE;
    protected $orderId;
    protected $accountNo;
    protected $clientIp;
    protected $channelExtra;

    public function getFunctionCode()
    {
        return EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_OPEN;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\ElectronicAccountBusinessOpenResponse::class;
    }

    public function getWayCode() { return $this->wayCode; }
    public function getOrderId() { return $this->orderId; }
    public function getAccountNo() { return $this->accountNo; }
    public function getClientIp() { return $this->clientIp; }
    public function getChannelExtra() { return $this->channelExtra; }

    public function setWayCode($v) { $this->wayCode = trim((string)$v); return $this; }
    public function setOrderId($v) { $this->orderId = trim((string)$v); return $this; }
    public function setAccountNo($v) { $this->accountNo = trim((string)$v); return $this; }
    public function setClientIp($v) { $this->clientIp = trim((string)$v); return $this; }

    public function setChannelExtra($value)
    {
        if ($value instanceof ElectronicAccountBusinessChannelExtra) {
            $this->channelExtra = $value->toJson();
            return $this;
        }
        $this->channelExtra = $this->normalizeJsonObject($value);
        return $this;
    }

    public function validate()
    {
        if ($this->wayCode !== self::WAY_CODE) {
            throw new LsPayValidationException('way_code 固定为 ' . self::WAY_CODE);
        }
        $this->assertRequiredString('order_id', $this->orderId, 30);
        $this->assertRequiredString('account_no', $this->accountNo, 32);
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
        if (!isset($extra['card_info']) || !is_array($extra['card_info'])) {
            throw new LsPayValidationException('channel_extra.card_info 必填');
        }
        if (!isset($extra['settle_config']) || !is_array($extra['settle_config'])) {
            throw new LsPayValidationException('channel_extra.settle_config 必填');
        }
        if (!isset($extra['cash_config'])
            || !is_array($extra['cash_config'])
            || $extra['cash_config'] === []
            || array_keys($extra['cash_config'])
                !== range(0, count($extra['cash_config']) - 1)) {
            throw new LsPayValidationException(
                'channel_extra.cash_config 必须是非空数组列表'
            );
        }

        $card = new ElectronicAccountSettlementCard();
        $cardSetters = [
            'card_type' => 'setCardType',
            'card_name' => 'setCardName',
            'card_no' => 'setCardNo',
            'prov_id' => 'setProvId',
            'area_id' => 'setAreaId',
            'bank_code' => 'setBankCode',
            'branch_code' => 'setBranchCode',
            'branch_name' => 'setBranchName',
            'cert_type' => 'setCertType',
            'cert_no' => 'setCertNo',
            'cert_validity_type' => 'setCertValidityType',
            'cert_begin_date' => 'setCertBeginDate',
            'cert_end_date' => 'setCertEndDate',
            'mp' => 'setMobile',
        ];
        $this->applySetters($card, $extra['card_info'], $cardSetters, 'card_info');
        $card->toArray();

        $settle = new ElectronicAccountSettleConfig();
        $this->applySetters(
            $settle,
            $extra['settle_config'],
            [
                'settle_cycle' => 'setSettleCycle',
                'settle_status' => 'setSettleStatus',
            ],
            'settle_config'
        );
        $settle->toArray();

        foreach ($extra['cash_config'] as $index => $item) {
            if (!is_array($item)) {
                throw new LsPayValidationException(
                    "channel_extra.cash_config[$index] 必须是 JSON 对象"
                );
            }
            $cash = new ElectronicAccountCashConfig();
            $this->applySetters(
                $cash,
                $item,
                [
                    'cash_type' => 'setCashType',
                    'switch_state' => 'setSwitchState',
                    'out_fee_flag' => 'setOutFeeFlag',
                ],
                "cash_config[$index]"
            );
            $cash->toArray();
        }
    }

    private function applySetters($builder, array $values, array $setters, $prefix)
    {
        foreach ($setters as $field => $setter) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            if ($values[$field] !== null
                && !is_string($values[$field])
                && !is_numeric($values[$field])) {
                throw new LsPayValidationException(
                    "channel_extra.$prefix.$field 必须是标量值"
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
