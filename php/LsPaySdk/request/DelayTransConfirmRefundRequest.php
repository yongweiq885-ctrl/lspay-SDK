<?php
/**
 * 交易确认退款请求。
 *
 * 端点：/gateway/delaytrans/confirmrefund
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class DelayTransConfirmRefundRequest extends BaseRequest
{
    /** @var string|null 交易确认流水号 */
    protected $confirmOrderId;

    /** @var string|null 商户生成的确认退款流水号 */
    protected $confirmRefundNo;

    /** @var string|null 特定渠道额外参数 JSON 对象字符串 */
    protected $channelExtra;

    public function getFunctionCode()
    {
        return EndpointEnum::DELAY_TRANS_CONFIRM_REFUND;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\DelayTransConfirmRefundResponse::class;
    }

    public function getConfirmOrderId() { return $this->confirmOrderId; }
    public function getConfirmRefundNo() { return $this->confirmRefundNo; }
    public function getChannelExtra() { return $this->channelExtra; }

    public function setConfirmOrderId($value)
    {
        $this->confirmOrderId = trim((string)$value);
        return $this;
    }

    public function setConfirmRefundNo($value)
    {
        $this->confirmRefundNo = trim((string)$value);
        return $this;
    }

    public function setChannelExtra($value)
    {
        $this->channelExtra = $this->normalizeJsonObject($value);
        return $this;
    }

    public function validate()
    {
        $this->assertRequiredString('confirm_order_id', $this->confirmOrderId, 30);
        $this->assertRequiredString('confirm_refund_no', $this->confirmRefundNo, 30);
        $this->assertOptionalString('channel_extra', $this->channelExtra, 256);
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
