<?php
/**
 * 电子账户余额查询示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\BalanceQueryRequest;

$request = (new BalanceQueryRequest())
    ->setOrderId('20160427210604000490')
    ->setAccountNo('313920933111042')
    ->setClientIp('210.73.10.148');

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "余额查询失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo "可用余额: " . $result->getAvailableBalance() . "\n";
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
