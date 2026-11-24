<?php
/**
 * 快捷支付短信校验请求。
 *
 * 与支付确认使用相同字段校验，端点为 /gateway/quickpay/smscheck。
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;

class QuickPaySmsCheckRequest extends QuickPayConfirmRequest
{
    public function getFunctionCode()
    {
        return EndpointEnum::QUICK_PAY_SMS_CHECK;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\QuickPaySmsCheckResponse::class;
    }
}
