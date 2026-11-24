<?php
/**
 * 可转余额查询请求。
 *
 * 查询当前商户所有延时分账订单的未分账总金额。
 * 端点：/gateway/delaytrans/unconfirm/query
 *
 * 本接口没有业务请求字段，商户号、应用 ID、请求时间、版本和签名均由 SDK 注入。
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;

class DelayTransUnconfirmQueryRequest extends BaseRequest
{
    public function getFunctionCode()
    {
        return EndpointEnum::DELAY_TRANS_UNCONFIRM_QUERY;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\DelayTransUnconfirmQueryResponse::class;
    }
}
