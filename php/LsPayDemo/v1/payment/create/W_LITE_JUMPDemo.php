<?php
/**
 * 自有微信小程序跳立收通收银台
 *
 * way_code: WX_LITE_JUMP
 * channel_extra 可选：{"openid":"..."}，只序列化一次。
 * 用 pay_info.app_id 和 pay_info.path 跳转。
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
        ->setWayCode(WayCodeEnum::WX_LITE_JUMP)
        ->setAmount(DemoConstants::DEMO_AMOUNT_FEN)
        ->setSubject(DemoConstants::DEMO_SUBJECT)
        ->setBody(DemoConstants::DEMO_BODY)
        ->setNotifyUrl(DemoConstants::DEMO_NOTIFY_URL)
        ->setChannelExtra(['openid' => 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o']);

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

echo "[WX_LITE_JUMP] 使用 app_id 和 path 跳转立收通收银台\n";
var_dump($payInfo);
