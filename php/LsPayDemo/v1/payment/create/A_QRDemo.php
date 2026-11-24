<?php
/**
 * 支付宝扫码 - 场景化示例
 *
 * way_code: ALI_QR
 * 适用：商户后台展示二维码，用户用支付宝 App 扫码完成支付
 *       也可用于 H5/APP 拉起支付链接跳转场景
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
        ->setWayCode(WayCodeEnum::ALI_QR)
        ->setAmount(DemoConstants::DEMO_AMOUNT_FEN)
        ->setSubject(DemoConstants::DEMO_SUBJECT)
        ->setBody(DemoConstants::DEMO_BODY)
        ->setNotifyUrl(DemoConstants::DEMO_NOTIFY_URL)
        ->setChannelExtra(['pay_data_type' => 'code_url']);

$client = new LsPayClient();
$result  = $client->postRequest($request);

if (!$result || $result->isError()) {
    var_dump($result ? $result->getErrorInfo() : 'null');
    exit;
}

// 网关返回的 pay_url / qr_code 可直接生成二维码展示
echo "[ALI_QR] 扫码支付信息:\n";
var_dump($result->getData());
