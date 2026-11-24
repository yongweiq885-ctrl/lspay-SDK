<?php
/**
 * 快捷支付绑卡申请示例。
 *
 * 请在服务端使用真实持卡人信息；严禁将银行卡四要素暴露到浏览器或日志。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\QuickPayBindApplyRequest;
use LsPaySdk\request\QuickPayBindChannelExtra;

$channelExtra = (new QuickPayBindChannelExtra())
    ->setMchUserId('YOUR_USER_ID')
    ->setCertType('00')
    ->setCardId('YOUR_BANK_CARD_NO')
    ->setCardName('持卡人姓名')
    ->setCertId('YOUR_CERTIFICATE_NO')
    ->setCertValidityType('0')
    ->setCertBeginDate('20200101')
    ->setCertEndDate('20400101')
    ->setCardMp('YOUR_BANK_MOBILE')
    ->setDcType('D');

$request = (new QuickPayBindApplyRequest())
    ->setOrderId('BIND' . date('YmdHis') . mt_rand(1000, 9999))
    ->setClientIp('127.0.0.1')
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "绑卡申请失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    if ($result->isSuccessful()) {
        echo "绑卡申请验证成功，请继续调用绑卡确认接口校验短信:\n";
    } elseif ($result->isProcessing()) {
        echo "绑卡申请处理中:\n";
    } else {
        echo "绑卡申请未成功:\n";
    }
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
