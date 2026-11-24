<?php
/**
 * 申请退款请求
 *   端点: /gateway/refund
 *
 * mch_order_no 与 pay_order_id 至少填写一个。
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class RefundRequest extends BaseRequest
{
    /** 商户退款单号（必填） */
    protected $mchRefundNo;
    /** 退款金额（必填，单位:分） */
    protected $refundAmount;
    /** 币种，默认 cny */
    protected $currency = 'cny';
    /** 退款原因（必填） */
    protected $refundReason;
    /** 原商户订单号 */
    protected $mchOrderNo;
    /** 原支付订单号 */
    protected $payOrderId;
    /** 客户端 IPv4 */
    protected $clientIp;
    /** 退款异步通知地址 */
    protected $notifyUrl;
    /** 渠道扩展参数（JSON 对象字符串） */
    protected $channelExtra;
    /** 商户扩展参数，回调原样返回 */
    protected $extParam;

    public function getFunctionCode() { return EndpointEnum::REFUND; }
    public function getResponseClass() { return \LsPaySdk\response\RefundResponse::class; }

    public function getMchRefundNo()  { return $this->mchRefundNo; }
    public function getRefundAmount() { return $this->refundAmount; }
    public function getCurrency()     { return $this->currency; }
    public function getRefundReason() { return $this->refundReason; }
    public function getMchOrderNo()   { return $this->mchOrderNo; }
    public function getPayOrderId()   { return $this->payOrderId; }
    public function getClientIp()     { return $this->clientIp; }
    public function getNotifyUrl()    { return $this->notifyUrl; }
    public function getChannelExtra() { return $this->channelExtra; }
    public function getExtParam()     { return $this->extParam; }

    public function setMchRefundNo($v)  { $this->mchRefundNo = trim((string)$v); return $this; }
    public function setRefundAmount($v) { $this->refundAmount = $this->parseInteger('refund_amount', $v); return $this; }
    public function setCurrency($v)     { $this->currency = strtolower(trim((string)$v)); return $this; }
    public function setRefundReason($v) { $this->refundReason = trim((string)$v); return $this; }
    public function setMchOrderNo($v)   { $this->mchOrderNo = trim((string)$v); return $this; }
    public function setPayOrderId($v)   { $this->payOrderId = trim((string)$v); return $this; }
    public function setClientIp($v)     { $this->clientIp = trim((string)$v); return $this; }
    public function setNotifyUrl($v)    { $this->notifyUrl = trim((string)$v); return $this; }

    /** @deprecated 使用 getPayOrderId() */
    public function getPayOrderNo() { return $this->getPayOrderId(); }

    /** @deprecated 使用 setPayOrderId() */
    public function setPayOrderNo($v) { return $this->setPayOrderId($v); }

    public function setChannelExtra($value)
    {
        $this->channelExtra = $this->normalizeJsonObject($value, 'channel_extra');
        return $this;
    }

    public function setExtParam($value)
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            if ($value === false) {
                throw new LsPayValidationException(
                    'ext_param JSON 编码失败: ' . json_last_error_msg()
                );
            }
        }
        $this->extParam = (string)$value;
        return $this;
    }

    public function validate()
    {
        $this->assertRequiredString('mch_refund_no', $this->mchRefundNo, 30);
        $this->assertOptionalString('mch_order_no', $this->mchOrderNo, 30);
        $this->assertOptionalString('pay_order_id', $this->payOrderId, 30);
        if (($this->mchOrderNo === null || $this->mchOrderNo === '')
            && ($this->payOrderId === null || $this->payOrderId === '')) {
            throw new LsPayValidationException(
                'mch_order_no 与 pay_order_id 至少填写一个'
            );
        }

        if (!is_int($this->refundAmount) || $this->refundAmount <= 0) {
            throw new LsPayValidationException(
                'refund_amount 必须是大于 0 的整数（单位：分）'
            );
        }
        if (!preg_match('/^[a-zA-Z]{3}$/', (string)$this->currency)) {
            throw new LsPayValidationException('currency 必须是 3 位字母货币代码');
        }
        $this->assertRequiredString('refund_reason', $this->refundReason, 64);
        $this->assertOptionalString('client_ip', $this->clientIp, 32);
        $this->assertOptionalString('notify_url', $this->notifyUrl, 128);
        $this->assertOptionalString('channel_extra', $this->channelExtra, 256);
        $this->assertOptionalString('ext_param', $this->extParam, 512);

        if ($this->clientIp !== null && $this->clientIp !== ''
            && filter_var($this->clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new LsPayValidationException('client_ip 必须是合法 IPv4 地址');
        }
        if ($this->notifyUrl !== null && $this->notifyUrl !== '') {
            $scheme = strtolower((string)parse_url($this->notifyUrl, PHP_URL_SCHEME));
            if (filter_var($this->notifyUrl, FILTER_VALIDATE_URL) === false
                || !in_array($scheme, ['http', 'https'], true)) {
                throw new LsPayValidationException(
                    'notify_url 必须是合法 HTTP(S) URL'
                );
            }
        }
    }

    private function normalizeJsonObject($value, $field)
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
                    "$field 必须是合法 JSON 对象字符串"
                );
            }
        } else {
            throw new LsPayValidationException(
                "$field 只支持关联数组、对象或 JSON 字符串"
            );
        }
        if ($decoded !== [] && array_keys($decoded) === range(0, count($decoded) - 1)) {
            throw new LsPayValidationException(
                "$field 必须是 JSON 对象，不能是数组列表"
            );
        }
        $json = json_encode(
            (object)$decoded,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            throw new LsPayValidationException(
                "$field JSON 编码失败: " . json_last_error_msg()
            );
        }
        return $json;
    }

    private function assertRequiredString($field, $value, $maxLength)
    {
        if (!is_string($value) && !is_numeric($value)) {
            throw new LsPayValidationException("$field 必填且必须是字符串");
        }
        if (trim((string)$value) === '') {
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

    private function parseInteger($field, $value)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new LsPayValidationException("$field 必须是整数");
        }
        return (int)$value;
    }
}
