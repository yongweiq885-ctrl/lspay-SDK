<?php
/**
 * 快捷支付解绑示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\QuickPayUnbindChannelExtra;
use LsPaySdk\request\QuickPayUnbindRequest;

$channelExtra = (new QuickPayUnbindChannelExtra())
    ->setMchUserId('YOUR_USER_ID')
    ->setChannelCardId('YOUR_CHANNEL_CARD_ID');

$request = (new QuickPayUnbindRequest())
    ->setOrderId('UNBIND' . date('YmdHis') . mt_rand(1000, 9999))
    ->setClientIp('127.0.0.1')
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "快捷支付解绑请求失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo $result->isSuccessful()
        ? "快捷支付解绑成功:\n"
        : "快捷支付解绑尚未成功:\n";
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
