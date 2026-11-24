<?php
/**
 * 快捷支付短信校验示例。
 *
 * 校验成功后仍需调用支付确认接口完成支付。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\QuickPaySmsCheckRequest;

$request = (new QuickPaySmsCheckRequest())
    ->setPayOrderId('YOUR_PAY_ORDER_ID')
    ->setVerifyCode('123456')
    ->setClientIp('127.0.0.1');

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "快捷支付短信校验请求失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    if ($result->isSuccessful()) {
        echo "短信验证码预校验成功，请继续调用支付确认接口:\n";
    } elseif ($result->isProcessing()) {
        echo "短信验证码正在校验:\n";
    } else {
        echo "短信验证码预校验失败:\n";
    }
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
