<?php
/**
 * 快捷支付绑卡确认示例。
 *
 * order_id 与持卡人四要素必须和绑卡申请保持一致。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\QuickPayBindConfirmChannelExtra;
use LsPaySdk\request\QuickPayBindConfirmRequest;

$channelExtra = (new QuickPayBindConfirmChannelExtra())
    ->setMchUserId('YOUR_USER_ID')
    ->setCertType('00')
    ->setCardId('YOUR_BANK_CARD_NO')
    ->setCardName('持卡人姓名')
    ->setCertId('YOUR_CERTIFICATE_NO')
    ->setCardMp('YOUR_BANK_MOBILE')
    ->setDcType('D');

$request = (new QuickPayBindConfirmRequest())
    ->setOrderId('BIND_APPLY_ORDER_ID')
    ->setVerifyCode('123456')
    ->setClientIp('127.0.0.1')
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "绑卡确认失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    if ($result->hasBinding()) {
        echo "绑卡成功，请加密保存 channel_user 与 channel_card_id:\n";
    } elseif ($result->isProcessing()) {
        echo "绑卡确认处理中:\n";
    } else {
        echo "绑卡确认未成功:\n";
    }
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
