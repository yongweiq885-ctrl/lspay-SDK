<?php
/**
 * 统一响应对象 - 封装网关返回数据
 *
 *   $result->isError()       是否业务失败
 *   $result->getErrorInfo()  错误信息(status/msg)
 *   $result->getSign()       响应 data 的签名（同步响应不验签）
 *   $result->getData()       业务数据
 *   $result->getRaw()        网关原始 JSON 解析后的数组
 */
namespace LsPaySdk\core;

class LsPayResponse
{
    private $raw;
    private $status;
    private $msg;
    private $sign;
    private $data;
    private $isError = false;

    public function __construct(array $raw)
    {
        $this->raw = $raw;
        $this->status = array_key_exists('status', $raw)
            ? $raw['status']
            : (array_key_exists('code', $raw)
                ? $raw['code']
                : (array_key_exists('retCode', $raw) ? $raw['retCode'] : null));
        $this->msg = array_key_exists('msg', $raw)
            ? $raw['msg']
            : (array_key_exists('retMsg', $raw) ? $raw['retMsg'] : '');
        $this->sign = array_key_exists('sign', $raw) ? $raw['sign'] : null;
        $this->data = array_key_exists('data', $raw) ? $raw['data'] : [];
        if (is_string($this->data) && trim($this->data) !== '') {
            $decodedData = json_decode($this->data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->data = $decodedData;
            }
        } elseif ($this->data === null || $this->data === '') {
            $this->data = [];
        }

        // 统一下单正式协议：status=200 成功。保留 code=0/SUCCESS 兼容尚未迁移的接口。
        $successValues = ['200', '0', 'SUCCESS'];
        $this->isError = $this->status === null
            || !in_array(strtoupper((string)$this->status), $successValues, true);
    }

    /** 是否业务失败 */
    public function isError() { return $this->isError; }

    /** 返回 status / msg 错误描述 */
    public function getErrorInfo()
    {
        return [
            'status' => $this->status,
            'msg'  => $this->msg,
        ];
    }

    /** 业务数据（网关 data 字段；JSON 字符串会自动解析） */
    public function getData() { return $this->data; }

    /** 网关原始数据 */
    public function getRaw() { return $this->raw; }

    public function getStatus() { return $this->status; }
    /** @deprecated 使用 getStatus() */
    public function getCode() { return $this->status; }
    public function getMsg()  { return $this->msg; }
    /** 同步响应签名仅透传，不执行验签。 */
    public function getSign() { return $this->sign; }
}
