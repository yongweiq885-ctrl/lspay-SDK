<?php
/**
 * Request 基类，所有接口请求继承自此
 */
namespace LsPaySdk\request;

abstract class BaseRequest
{
    /** 业务扩展字段（透传到网关，未在子类显式定义的参数） */
    protected $extendInfos = [];

    public function getExtendInfos() { return $this->extendInfos; }

    public function setExtendInfo(array $extendInfos)
    {
        $this->extendInfos = $extendInfos;
        return $this;
    }

    /**
     * 发起请求前校验参数。子类按接口契约覆盖。
     *
     * @return void
     */
    public function validate()
    {
    }

    /** 返回当前接口对应的响应类。 */
    public function getResponseClass()
    {
        return \LsPaySdk\core\LsPayResponse::class;
    }

    /**
     * 返回当前请求对应的接口功能编码（端点路径）
     * 子类必须实现。
     *
     * @return string
     */
    abstract public function getFunctionCode();
}
