<?php
/**
 * 快捷支付确认示例。
 *
 * 使用支付申请返回的 pay_order_id 和银行短信验证码完成支付。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\QuickPayConfirmRequest;

$request = (new QuickPayConfirmRequest())
    ->setPayOrderId('YOUR_PAY_ORDER_ID')
    ->setVerifyCode('123456')
    ->setClientIp('127.0.0.1');

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "快捷支付确认请求失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    if ($result->isSuccessful()) {
        echo "快捷支付确认成功:\n";
    } elseif ($result->isProcessing()) {
        echo "支付验证处理中，最终结果以异步通知为准:\n";
    } else {
        echo "快捷支付确认失败:\n";
    }
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
