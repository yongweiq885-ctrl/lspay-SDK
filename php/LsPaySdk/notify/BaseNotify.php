<?php
/**
 * 立收通异步通知基类。
 *
 * 构造时先使用原始表单字段验签，再校验商户身份。所有未知字段都会保留，
 * 并已参与验签，便于平台后续增加通知参数。
 */
namespace LsPaySdk\notify;

use LsPaySdk\config\MerConfig;
use LsPaySdk\core\LsPayTools;
use LsPaySdk\exceptions\LsPaySignException;
use LsPaySdk\exceptions\LsPayValidationException;

abstract class BaseNotify
{
    /** @var array<string,string|int|float|bool> */
    protected $data;

    public function __construct(array $data, MerConfig $config)
    {
        if (!$data) {
            throw new LsPayValidationException('通知参数不能为空');
        }
        foreach ($data as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new LsPayValidationException('通知参数名必须是非空字符串');
            }
            if (is_array($value) || is_object($value) || is_resource($value)) {
                throw new LsPayValidationException(
                    "通知参数 $key 必须是标量值"
                );
            }
        }

        $sign = isset($data['sign']) ? (string)$data['sign'] : '';
        if (!preg_match('/^[A-Fa-f0-9]{32}$/', $sign)) {
            throw new LsPaySignException('通知缺少合法的 32 位 MD5 签名');
        }
        if (!LsPayTools::verifySign($data, $config->md5_key)) {
            throw new LsPaySignException('通知签名验证失败');
        }

        $mchNo = isset($data['mch_no']) ? (string)$data['mch_no'] : '';
        $appId = isset($data['app_id']) ? (string)$data['app_id'] : '';
        if ($mchNo === '' || $appId === '') {
            throw new LsPayValidationException('通知缺少 mch_no 或 app_id');
        }
        if (!hash_equals((string)$config->mch_no, $mchNo)
            || !hash_equals((string)$config->app_id, $appId)) {
            throw new LsPayValidationException('通知商户身份与 SDK 配置不匹配');
        }

        $this->data = $data;
    }

    public function getRaw()
    {
        return $this->data;
    }

    public function getNotifyType() { return $this->value('notify_type'); }
    public function getEventType() { return $this->value('event_type'); }
    public function getPayOrderId() { return $this->value('pay_order_id'); }
    public function getMchNo() { return $this->value('mch_no'); }
    public function getAppId() { return $this->value('app_id'); }
    public function getWayCode() { return $this->value('way_code'); }
    public function getCurrency() { return $this->value('currency'); }
    public function getExtParam() { return $this->value('ext_param'); }
    public function getAddTime() { return $this->value('add_time'); }
    public function getSuccessTime() { return $this->value('success_time'); }
    public function getReqTime() { return $this->value('req_time'); }
    public function getSign() { return $this->value('sign'); }
    public function getState() { return $this->integerValue('state'); }

    abstract public function getIdempotencyKey();

    protected function value($key)
    {
        return array_key_exists($key, $this->data)
            ? (string)$this->data[$key]
            : null;
    }

    protected function integerValue($key)
    {
        $value = $this->value($key);
        return $value === null || !preg_match('/^\d+$/', $value)
            ? null
            : (int)$value;
    }

    protected function requireString($field, $maxLength, $allowEmpty = false)
    {
        if (!array_key_exists($field, $this->data)) {
            throw new LsPayValidationException("通知缺少 $field");
        }
        $value = (string)$this->data[$field];
        if (!$allowEmpty && trim($value) === '') {
            throw new LsPayValidationException("通知字段 $field 不能为空");
        }
        if ($this->stringLength($value) > $maxLength) {
            throw new LsPayValidationException(
                "通知字段 $field 长度不能超过 $maxLength"
            );
        }
        return $value;
    }

    protected function optionalString($field, $maxLength)
    {
        if (!array_key_exists($field, $this->data)
            || $this->data[$field] === '') {
            return null;
        }
        $value = (string)$this->data[$field];
        if ($this->stringLength($value) > $maxLength) {
            throw new LsPayValidationException(
                "通知字段 $field 长度不能超过 $maxLength"
            );
        }
        return $value;
    }

    protected function requirePositiveInteger($field)
    {
        $value = $this->requireString($field, 20);
        if (!preg_match('/^\d+$/', $value) || (int)$value <= 0) {
            throw new LsPayValidationException(
                "通知字段 $field 必须是大于 0 的整数"
            );
        }
        return (int)$value;
    }

    protected function requireState(array $states)
    {
        $value = $this->requireString('state', 2);
        if (!preg_match('/^\d+$/', $value)
            || !in_array((int)$value, $states, true)) {
            throw new LsPayValidationException('通知字段 state 非法');
        }
        return (int)$value;
    }

    protected function requireDigits($field, $minLength, $maxLength)
    {
        $value = $this->requireString($field, $maxLength);
        $length = strlen($value);
        if (!ctype_digit($value)
            || $length < $minLength
            || $length > $maxLength) {
            $span = $minLength === $maxLength
                ? "{$minLength} 位"
                : "{$minLength}-{$maxLength} 位";
            throw new LsPayValidationException(
                "通知字段 $field 必须是 {$span}数字"
            );
        }
        return $value;
    }

    protected function assertCurrency()
    {
        $currency = $this->requireString('currency', 3);
        if (!preg_match('/^[a-zA-Z]{3}$/', $currency)) {
            throw new LsPayValidationException(
                '通知字段 currency 必须是 3 位字母货币代码'
            );
        }
    }

    protected function assertEventPrefix($prefix)
    {
        foreach (['notify_type', 'event_type'] as $field) {
            $value = $this->optionalString($field, 64);
            if ($value !== null && strpos($value, $prefix) !== 0) {
                throw new LsPayValidationException(
                    "通知字段 $field 与通知类型不匹配"
                );
            }
        }
    }

    protected function assertExpectedInteger($field, $expected)
    {
        if (filter_var($expected, FILTER_VALIDATE_INT) === false
            || (int)$expected !== $this->integerValue($field)) {
            throw new LsPayValidationException(
                "通知字段 $field 与商户订单记录不一致"
            );
        }
        return $this;
    }

    protected function assertExpectedString($field, $expected)
    {
        $actual = $this->value($field);
        if ($actual === null
            || !hash_equals((string)$expected, $actual)) {
            throw new LsPayValidationException(
                "通知字段 $field 与商户订单记录不一致"
            );
        }
        return $this;
    }

    private function stringLength($value)
    {
        return function_exists('mb_strlen')
            ? mb_strlen((string)$value, 'UTF-8')
            : strlen((string)$value);
    }
}
