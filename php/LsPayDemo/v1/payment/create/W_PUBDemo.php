<?php
/**
 * 微信 JSAPI - 场景化示例
 *
 * way_code: WX_PUB
 * 适用：公众号或微信内 H5 唤起支付；必须通过 channel_extra 传 openid。
 * 微信扫码请使用 WX_NATIVE。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPayDemo\constants\DemoConstants;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\PayDataTypeEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\request\UnifiedOrderChannelExtra;
use LsPaySdk\request\UnifiedOrderRequest;

$openid = getenv('LSPAY_DEMO_OPENID');
if ($openid === false || trim($openid) === '') {
    exit("请先设置环境变量 LSPAY_DEMO_OPENID（公众号授权获得的 openid）。\n");
}

$channelExtra = (new UnifiedOrderChannelExtra())
    ->setOpenid($openid)
    ->setPayDataType(PayDataTypeEnum::WXAPP);

$request = new UnifiedOrderRequest();
$request->setMchOrderNo(DemoConstants::buildOrderNo())
        ->setWayCode(WayCodeEnum::WX_PUB)
        ->setAmount(DemoConstants::DEMO_AMOUNT_FEN)
        ->setSubject(DemoConstants::DEMO_SUBJECT)
        ->setBody(DemoConstants::DEMO_BODY)
        ->setNotifyUrl(DemoConstants::DEMO_NOTIFY_URL)
        ->setChannelExtra($channelExtra);

$client = new LsPayClient();
$result  = $client->postRequest($request);

if (!$result || $result->isError()) {
    var_dump($result ? $result->getErrorInfo() : 'null');
    exit;
}

echo "[WX_PUB] 微信 JSAPI 支付信息:\n";
var_dump($result->getData());
