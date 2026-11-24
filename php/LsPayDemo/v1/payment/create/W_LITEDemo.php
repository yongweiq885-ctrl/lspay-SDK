<?php
/**
 * 微信小程序原生支付
 *
 * way_code: WX_LITE
 * channel_extra 只序列化一次：{"openid":"..."}
 * 响应 pay_info 是 JSON 字符串，解析后交给 wx.requestPayment。signType 用返回值。
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
        ->setWayCode(WayCodeEnum::WX_LITE)
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

echo "[WX_LITE] 调起参数:\n";
var_dump($payInfo);
