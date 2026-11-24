<?php
/**
 * 立收通统一收银台 - 场景化示例
 *
 * channel_extra.pay_data_type: cashier
 * 网关直接返回收银台页面，不返回 JSON。服务端只负责生成签名表单，
 * 不要用 postRequest() 调用。输出的表单会自动提交，浏览器直接跳转收银台。
 *
 * 用浏览器访问本文件即可直接跳转到收银台，接入时把它放在商户“去支付”的处理逻辑里。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPayDemo\constants\DemoConstants;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\PayDataTypeEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\UnifiedOrderRequest;

$request = new UnifiedOrderRequest();
$request->setMchOrderNo(DemoConstants::buildOrderNo())
        ->setWayCode(WayCodeEnum::ALI_QR)
        ->setAmount(DemoConstants::DEMO_AMOUNT_FEN)
        ->setSubject(DemoConstants::DEMO_SUBJECT)
        ->setBody(DemoConstants::DEMO_BODY)
        ->setNotifyUrl(DemoConstants::DEMO_NOTIFY_URL)
        ->setReturnUrl(DemoConstants::DEMO_RETURN_URL)
        ->setChannelExtra(['pay_data_type' => PayDataTypeEnum::CASHIER]);

header('Content-Type: text/html; charset=utf-8');
try {
    echo (new LsPayClient())->renderCashierForm($request);
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
