<?php
/**
 * 支付宝 H5/APP 收银台 - 场景化示例
 *
 * way_code: ALI_LITE_H5
 * 适用：H5 / APP 中拉起支付宝收银台页面（非直连场景）
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPayDemo\constants\DemoConstants;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\request\UnifiedOrderRequest;

$request = new UnifiedOrderRequest();
$request->setMchOrderNo(DemoConstants::buildOrderNo())
        ->setWayCode(WayCodeEnum::ALI_LITE_H5)
        ->setAmount(DemoConstants::DEMO_AMOUNT_FEN)
        ->setSubject(DemoConstants::DEMO_SUBJECT)
        ->setBody(DemoConstants::DEMO_BODY)
        ->setClientIp('114.114.114.114')
        ->setNotifyUrl(DemoConstants::DEMO_NOTIFY_URL)
        ->setReturnUrl('https://your.domain/pay_result.html')
        ->setChannelExtra(['pay_data_type' => 'pay_url']);

$client = new LsPayClient();
$result  = $client->postRequest($request);

if (!$result || $result->isError()) {
    var_dump($result ? $result->getErrorInfo() : 'null');
    exit;
}

echo "[ALI_LITE_H5] App 只用 schema 打开 jump_url。下单 app_schema 要带 ://，App 注册的 scheme 不要带\n";
var_dump($result->getData());
