<?php
/**
 * 电子账户用户查询示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\ElectronicAccountUserQueryRequest;

$request = (new ElectronicAccountUserQueryRequest())
    ->setAccountNo('YOUR_ELECTRONIC_ACCOUNT_NO')
    ->setClientIp('127.0.0.1');

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "电子账户用户查询失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo $result->isSuccessful()
        ? "用户查询成功:\n"
        : "用户查询未成功:\n";
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
