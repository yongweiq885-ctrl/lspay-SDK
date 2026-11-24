<?php
/**
 * 统一下单请求。端点 /gateway/unifiedorder。
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\PayDataTypeEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class UnifiedOrderRequest extends BaseRequest
{
    protected $mchOrderNo;
    protected $wayCode;
    /** 单位：分 */
    protected $amount;
    protected $currency = 'cny';
    protected $subject;
    protected $body;
    /** JSON 字符串或数组 */
    protected $channelExtra;
    protected $clientIp;
    protected $notifyUrl;
    protected $returnUrl;
    /** 单位：秒。平台默认 7200 */
    protected $expiredTime;
    /** 0 不分账，1 实时分账，2 延时分账 */
    protected $divisionMode;
    /** 回调时原样返回 */
    protected $extParam;
    /** 0 关闭，1 自然轮询，2 权重轮询，3 防断链轮询 */
    protected $pollingMode;

    public function getFunctionCode() { return EndpointEnum::UNIFIED_ORDER; }
    public function getResponseClass() { return \LsPaySdk\response\UnifiedOrderResponse::class; }

    public function getMchOrderNo()   { return $this->mchOrderNo; }
    public function getWayCode()      { return $this->wayCode; }
    public function getAmount()       { return $this->amount; }
    public function getCurrency()     { return $this->currency; }
    public function getSubject()      { return $this->subject; }
    public function getBody()         { return $this->body; }
    public function getChannelExtra() { return $this->channelExtra; }
    public function getClientIp()     { return $this->clientIp; }
    public function getNotifyUrl()    { return $this->notifyUrl; }
    public function getReturnUrl()    { return $this->returnUrl; }
    public function getExpiredTime()  { return $this->expiredTime; }
    public function getDivisionMode() { return $this->divisionMode; }
    public function getExtParam()     { return $this->extParam; }
    public function getPollingMode()  { return $this->pollingMode; }

    public function setMchOrderNo($v)   { $this->mchOrderNo = trim((string)$v); return $this; }
    public function setWayCode($v)      { $this->wayCode = trim((string)$v); return $this; }
    public function setAmount($v)       { $this->amount = $this->parseInteger('amount', $v); return $this; }
    public function setCurrency($v)     { $this->currency = strtolower(trim((string)$v)); return $this; }
    public function setSubject($v)      { $this->subject = $v; return $this; }
    public function setBody($v)         { $this->body = $v; return $this; }
    public function setClientIp($v)     { $this->clientIp = trim((string)$v); return $this; }
    public function setNotifyUrl($v)    { $this->notifyUrl = trim((string)$v); return $this; }
    public function setReturnUrl($v)    { $this->returnUrl = trim((string)$v); return $this; }
    public function setExpiredTime($v)  { $this->expiredTime = $this->parseInteger('expired_time', $v); return $this; }
    public function setDivisionMode($v) { $this->divisionMode = $this->parseInteger('division_mode', $v); return $this; }
    public function setPollingMode($v)  { $this->pollingMode = $this->parseInteger('polling_mode', $v); return $this; }

    /**
     * 渠道扩展参数。
     *
     * 支持 DivisionChannelExtra 及其子类、关联数组或 JSON 对象字符串；
     * 统一压缩为无多余空格的 JSON，签名和发送使用同一个字符串。
     */
    public function setChannelExtra($v)
    {
        if ($v instanceof DivisionChannelExtra) {
            $this->channelExtra = $v->toJson();
            return $this;
        }
        $this->channelExtra = $this->normalizeJsonObject($v, 'channel_extra');
        return $this;
    }

    /** 商户扩展参数：字符串原样返回；数组/对象仅作为便捷输入自动 JSON 化。 */
    public function setExtParam($v)
    {
        if (is_array($v) || is_object($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($v === false) {
                throw new LsPayValidationException(
                    'ext_param JSON 编码失败: ' . json_last_error_msg()
                );
            }
        }
        $this->extParam = (string)$v;
        return $this;
    }

    /**
     * @return void
     * @throws LsPayValidationException
     */
    public function validate()
    {
        $this->assertRequiredString('mch_order_no', $this->mchOrderNo, 30);
        $this->assertRequiredString('way_code', $this->wayCode, 30);
        if (!WayCodeEnum::isValid($this->wayCode)) {
            throw new LsPayValidationException('way_code 不在立收通支付方式清单中');
        }

        if (!is_int($this->amount) || $this->amount <= 0) {
            throw new LsPayValidationException('amount 必须是大于 0 的整数（单位：分）');
        }
        if (!preg_match('/^[a-zA-Z]{3}$/', (string)$this->currency)) {
            throw new LsPayValidationException('currency 必须是 3 位字母货币代码');
        }

        $this->assertRequiredString('subject', $this->subject, 64);
        $this->assertRequiredString('body', $this->body, 256);
        $this->assertOptionalString('client_ip', $this->clientIp, 32);
        $this->assertOptionalString('notify_url', $this->notifyUrl, 128);
        $this->assertOptionalString('return_url', $this->returnUrl, 128);
        $this->assertOptionalString('ext_param', $this->extParam, 512);

        if ($this->clientIp !== null && $this->clientIp !== ''
            && filter_var($this->clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new LsPayValidationException('client_ip 必须是合法 IPv4 地址');
        }
        $this->assertUrl('notify_url', $this->notifyUrl);
        $this->assertUrl('return_url', $this->returnUrl);

        if ($this->expiredTime !== null && $this->expiredTime <= 0) {
            throw new LsPayValidationException('expired_time 必须是大于 0 的秒数');
        }
        if ($this->divisionMode !== null
            && !in_array($this->divisionMode, [0, 1, 2], true)) {
            throw new LsPayValidationException('division_mode 只允许 0、1、2');
        }
        if ($this->pollingMode !== null
            && !in_array($this->pollingMode, [0, 1, 2, 3], true)) {
            throw new LsPayValidationException('polling_mode 只允许 0、1、2、3');
        }

        $extra = $this->validateChannelExtra();
        $requiredExtra = [
            WayCodeEnum::ALI_LITE => 'buyer_id',
            WayCodeEnum::WX_LITE  => 'openid',
            WayCodeEnum::WX_BAR   => 'auth_code',
            WayCodeEnum::ALI_BAR  => 'auth_code',
            WayCodeEnum::YSF_BAR  => 'auth_code',
        ];
        $payDataType = isset($extra['pay_data_type']) ? trim((string)$extra['pay_data_type']) : '';
        if ($payDataType === '') {
            $requiredExtra[WayCodeEnum::ALI_PUB] = 'buyer_id';
            $requiredExtra[WayCodeEnum::WX_PUB] = 'openid';
        }
        if (isset($requiredExtra[$this->wayCode])) {
            $field = $requiredExtra[$this->wayCode];
            if (!isset($extra[$field]) || trim((string)$extra[$field]) === '') {
                throw new LsPayValidationException(
                    "way_code={$this->wayCode} 时 channel_extra.{$field} 必填"
                );
            }
        }
        if ($this->wayCode === WayCodeEnum::BANK_FAST) {
            foreach (['channel_user', 'channel_card_id'] as $field) {
                if (!isset($extra[$field])
                    || (!is_string($extra[$field]) && !is_numeric($extra[$field]))
                    || trim((string)$extra[$field]) === '') {
                    throw new LsPayValidationException(
                        "way_code=BANK_FAST 时 channel_extra.$field 必填且必须是字符串"
                    );
                }
            }
        }
        if (in_array($this->divisionMode, [1, 2], true)
            && (!isset($extra['div_list']) || $extra['div_list'] === [])) {
            throw new LsPayValidationException(
                'division_mode 为 1 或 2 时 channel_extra.div_list 必填且不能为空'
            );
        }
    }

    private function validateChannelExtra()
    {
        if ($this->channelExtra === null || trim((string)$this->channelExtra) === '') {
            throw new LsPayValidationException('channel_extra 必填');
        }
        $this->assertOptionalString('channel_extra', $this->channelExtra, 256);
        $extra = json_decode($this->channelExtra, true);
        if (!is_array($extra) || json_last_error() !== JSON_ERROR_NONE) {
            throw new LsPayValidationException('channel_extra 必须是 JSON 对象字符串');
        }
        if (isset($extra['pay_data_type'])
            && !PayDataTypeEnum::isValid($extra['pay_data_type'])) {
            throw new LsPayValidationException(
                'channel_extra.pay_data_type 非法: ' . (string)$extra['pay_data_type']
            );
        }
        if (array_key_exists('div_list', $extra)) {
            $this->validateDivisionList($extra['div_list']);
        }
        return $extra;
    }

    private function validateDivisionList($divisionList)
    {
        if (!is_array($divisionList)
            || ($divisionList !== [] && array_keys($divisionList) !== range(0, count($divisionList) - 1))) {
            throw new LsPayValidationException('channel_extra.div_list 必须是数组列表');
        }
        foreach ($divisionList as $index => $receiver) {
            $field = "channel_extra.div_list[$index]";
            if (!is_array($receiver)
                || ($receiver !== [] && array_keys($receiver) === range(0, count($receiver) - 1))) {
                throw new LsPayValidationException("$field 必须是 JSON 对象");
            }
            if (!array_key_exists('trans_in', $receiver)
                || !is_string($receiver['trans_in'])
                || trim((string)$receiver['trans_in']) === '') {
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
                throw new LsPayValidationException("$field 必须是合法 JSON 对象字符串");
            }
        } else {
            throw new LsPayValidationException("$field 只支持关联数组、对象或 JSON 字符串");
        }

        if ($decoded !== [] && array_keys($decoded) === range(0, count($decoded) - 1)) {
            throw new LsPayValidationException("$field 必须是 JSON 对象，不能是数组列表");
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
        $value = (string)$value;
        if (trim($value) === '') {
            throw new LsPayValidationException("$field 必填");
        }
        if ($this->stringLength($value) > $maxLength) {
            throw new LsPayValidationException("$field 长度不能超过 $maxLength");
        }
    }

    private function assertOptionalString($field, $value, $maxLength)
    {
        if ($value === null || $value === '') {
            return;
        }
        if (!is_string($value) && !is_numeric($value)) {
            throw new LsPayValidationException("$field 必须是字符串");
        }
        if ($this->stringLength((string)$value) > $maxLength) {
            throw new LsPayValidationException("$field 长度不能超过 $maxLength");
        }
    }

    private function assertUrl($field, $value)
    {
        if ($value === null || $value === '') {
            return;
        }
        $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
        if (filter_var($value, FILTER_VALIDATE_URL) === false
            || !in_array($scheme, ['http', 'https'], true)) {
            throw new LsPayValidationException("$field 必须是合法 HTTP(S) URL");
        }
    }

    private function stringLength($value)
    {
        return function_exists('mb_strlen')
            ? mb_strlen((string)$value, 'UTF-8')
            : strlen((string)$value);
    }

    private function parseInteger($field, $value)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new LsPayValidationException("$field 必须是整数");
        }
        return (int)$value;
    }
}
