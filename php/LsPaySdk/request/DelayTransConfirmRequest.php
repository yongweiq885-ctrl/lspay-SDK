<?php
/**
 * 延时分账交易确认请求。
 *
 * 端点：/gateway/delaytrans/confirm
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class DelayTransConfirmRequest extends BaseRequest
{
    /** @var string|null 支付中心订单号，与 mch_order_no 二选一 */
    protected $payOrderId;

    /** @var string|null 商户支付订单号，与 pay_order_id 二选一 */
    protected $mchOrderNo;

    /** @var string|null 商户生成的本次确认流水号 */
    protected $orderId;

    /** @var string|null 分账渠道参数 JSON 对象字符串 */
    protected $channelExtra;

    public function getFunctionCode() { return EndpointEnum::DELAY_TRANS_CONFIRM; }
    public function getResponseClass()
    {
        return \LsPaySdk\response\DelayTransConfirmResponse::class;
    }

    public function getPayOrderId() { return $this->payOrderId; }
    public function getMchOrderNo() { return $this->mchOrderNo; }
    public function getOrderId() { return $this->orderId; }
    public function getChannelExtra() { return $this->channelExtra; }

    public function setPayOrderId($value)
    {
        $this->payOrderId = trim((string)$value);
        return $this;
    }

    public function setMchOrderNo($value)
    {
        $this->mchOrderNo = trim((string)$value);
        return $this;
    }

    public function setOrderId($value)
    {
        $this->orderId = trim((string)$value);
        return $this;
    }

    /**
     * @param DivisionChannelExtra|array<string,mixed>|object|string|null $value
     * @return $this
     */
    public function setChannelExtra($value)
    {
        if ($value instanceof DivisionChannelExtra) {
            $this->channelExtra = $value->toJson();
            return $this;
        }
        $this->channelExtra = $this->normalizeJsonObject($value);
        return $this;
    }

    public function validate()
    {
        $this->assertOptionalString('pay_order_id', $this->payOrderId, 30);
        $this->assertOptionalString('mch_order_no', $this->mchOrderNo, 30);
        if (($this->payOrderId === null || $this->payOrderId === '')
            && ($this->mchOrderNo === null || $this->mchOrderNo === '')) {
            throw new LsPayValidationException(
                'pay_order_id 与 mch_order_no 至少填写一个'
            );
        }
        $this->assertRequiredString('order_id', $this->orderId, 30);
        $this->assertOptionalString('channel_extra', $this->channelExtra, 256);

        if ($this->channelExtra !== null && $this->channelExtra !== '') {
            $extra = json_decode($this->channelExtra, true);
            if (!is_array($extra) || json_last_error() !== JSON_ERROR_NONE) {
                throw new LsPayValidationException(
                    'channel_extra 必须是 JSON 对象字符串'
                );
            }
            if (array_key_exists('div_list', $extra)) {
                $this->validateDivisionList($extra['div_list']);
            }
        }
    }

    private function validateDivisionList($divisionList)
    {
        if (!is_array($divisionList) || $divisionList === []
            || array_keys($divisionList) !== range(0, count($divisionList) - 1)) {
            throw new LsPayValidationException(
                'channel_extra.div_list 必须是非空数组列表'
            );
        }
        foreach ($divisionList as $index => $receiver) {
            $field = "channel_extra.div_list[$index]";
            if (!is_array($receiver)
                || ($receiver !== [] && array_keys($receiver) === range(0, count($receiver) - 1))) {
                throw new LsPayValidationException("$field 必须是 JSON 对象");
            }
            if (!array_key_exists('trans_in', $receiver)
                || !is_string($receiver['trans_in'])
                || trim($receiver['trans_in']) === '') {
                throw new LsPayValidationException("$field.trans_in 必填且必须是字符串");
            }
            if (array_key_exists('div_amt', $receiver)
                && (!is_int($receiver['div_amt']) || $receiver['div_amt'] <= 0)) {
                throw new LsPayValidationException(
                    "$field.div_amt 必须是大于 0 的整数（单位：分）"
                );
            }
            if (array_key_exists('description', $receiver)
                && !is_string($receiver['description'])) {
                throw new LsPayValidationException("$field.description 必须是字符串");
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
