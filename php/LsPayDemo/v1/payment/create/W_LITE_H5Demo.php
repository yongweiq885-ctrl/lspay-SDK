<?php
/**
 * 微信 App / H5 跳转立收通收银台
 *
 * way_code: WX_LITE_H5
 * 直连 App 请使用 WX_APP；小程序内跳收银台请使用 WX_LITE_JUMP。
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
        ->setWayCode(WayCodeEnum::WX_LITE_H5)
        ->setAmount(DemoConstants::DEMO_AMOUNT_FEN)
        ->setSubject(DemoConstants::DEMO_SUBJECT)
        ->setBody(DemoConstants::DEMO_BODY)
        ->setClientIp('114.114.114.114')
        ->setNotifyUrl(DemoConstants::DEMO_NOTIFY_URL)
        ->setReturnUrl(DemoConstants::DEMO_RETURN_URL)
        ->setChannelExtra(['pay_data_type' => 'pay_url']);

$client = new LsPayClient();
$result = $client->postRequest($request);

if (!$result || $result->isError()) {
    var_dump($result ? $result->getErrorInfo() : 'null');
    exit;
}

$payInfo = $result->getPayInfo();
if (is_string($payInfo)) {
    $payInfo = json_decode($payInfo, true);
}
$payOrderId = $result->getPayOrderId();
if (is_array($payInfo) && isset($payInfo['path'])) {
    $payInfo['path'] = $payInfo['path'] . '?p=' . $payOrderId . '&s=app';
}

echo "[WX_LITE_H5] App 使用 gh_id 和 path；H5 打开 scheme_code、pay_url 或 to_lite_url\n";
var_dump($payInfo);
