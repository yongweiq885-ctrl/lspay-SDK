<?php
/**
 * 快捷支付绑卡确认请求。
 *
 * 端点：/gateway/quickpay/buckleconfirm
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class QuickPayBindConfirmRequest extends BaseRequest
{
    protected $orderId;
    protected $wayCode = WayCodeEnum::BANK_FAST;
    protected $clientIp;
    protected $channelExtra;
    protected $verifyCode;

    public function getFunctionCode() { return EndpointEnum::QUICK_PAY_BIND_CONFIRM; }
    public function getResponseClass()
    {
        return \LsPaySdk\response\QuickPayBindConfirmResponse::class;
    }

    public function getOrderId() { return $this->orderId; }
    public function getWayCode() { return $this->wayCode; }
    public function getClientIp() { return $this->clientIp; }
    public function getChannelExtra() { return $this->channelExtra; }
    public function getVerifyCode() { return $this->verifyCode; }

    public function setOrderId($value) { $this->orderId = trim((string)$value); return $this; }
    public function setClientIp($value) { $this->clientIp = trim((string)$value); return $this; }
    public function setVerifyCode($value)
    {
        $this->verifyCode = trim((string)$value);
        return $this;
    }

    public function setWayCode($value)
    {
        $value = trim((string)$value);
        if ($value !== WayCodeEnum::BANK_FAST) {
            throw new LsPayValidationException('way_code 固定为 BANK_FAST');
        }
        $this->wayCode = $value;
        return $this;
    }

    public function setChannelExtra($value)
    {
        if ($value instanceof QuickPayBindConfirmChannelExtra) {
            $this->channelExtra = $value->toJson();
            return $this;
        }
        $this->channelExtra = $this->normalizeJsonObject($value);
        return $this;
    }

    public function validate()
    {
        $this->assertRequiredString('order_id', $this->orderId, 30);
        if ($this->wayCode !== WayCodeEnum::BANK_FAST) {
            throw new LsPayValidationException('way_code 固定为 BANK_FAST');
        }
        $this->assertOptionalString('client_ip', $this->clientIp, 32);
        if ($this->clientIp !== null && $this->clientIp !== ''
            && filter_var($this->clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new LsPayValidationException('client_ip 必须是合法 IPv4 地址');
        }
        $this->assertRequiredString('channel_extra', $this->channelExtra, 256);
        $this->validateChannelExtra();
        if (!preg_match('/^\d{3,8}$/', (string)$this->verifyCode)) {
            throw new LsPayValidationException('verify_code 必须是 3-8 位数字');
        }
    }

    private function validateChannelExtra()
    {
        $extra = json_decode((string)$this->channelExtra, true);
        if (!is_array($extra) || json_last_error() !== JSON_ERROR_NONE) {
            throw new LsPayValidationException('channel_extra 必须是 JSON 对象字符串');
        }
        $builder = new QuickPayBindConfirmChannelExtra();
        $setters = [
            'mch_user_id' => 'setMchUserId',
            'cert_type' => 'setCertType',
            'card_id' => 'setCardId',
            'card_name' => 'setCardName',
            'cert_id' => 'setCertId',
            'card_mp' => 'setCardMp',
            'dc_type' => 'setDcType',
        ];
        foreach ($setters as $field => $setter) {
            if (array_key_exists($field, $extra)) {
                if ($extra[$field] !== null
                    && !is_string($extra[$field])
                    && !is_numeric($extra[$field])) {
                    throw new LsPayValidationException(
                        "channel_extra.$field 必须是字符串"
                    );
                }
                if ($extra[$field] !== null) {
                    $builder->$setter($extra[$field]);
                }
            }
        }
        $builder->toArray();
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
