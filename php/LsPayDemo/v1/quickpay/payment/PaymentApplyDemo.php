<?php
/**
 * 快捷支付申请示例。
 *
 * channel_user 与 channel_card_id 必须取自绑卡确认成功响应。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\QuickPayApplyRequest;
use LsPaySdk\request\UnifiedOrderChannelExtra;

$channelExtra = (new UnifiedOrderChannelExtra())
    ->setChannelUser('YOUR_CHANNEL_USER')
    ->setChannelCardId('YOUR_CHANNEL_CARD_ID');

$request = (new QuickPayApplyRequest())
    ->setMchOrderNo('QPAY' . date('YmdHis') . mt_rand(1000, 9999))
    ->setAmount(100)
    ->setCurrency('cny')
    ->setSubject('快捷支付测试')
    ->setBody('快捷支付申请测试')
    ->setClientIp('127.0.0.1')
    ->setNotifyUrl('https://your.domain/notify.php')
    ->setExpiredTime(3600)
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "快捷支付申请失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    if ($result->isProcessing()) {
        echo "支付申请已受理，请继续调用支付确认接口校验短信:\n";
    } elseif ($result->isPaid()) {
        echo "订单已支付:\n";
    } else {
        echo "支付申请状态待确认:\n";
    }
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
