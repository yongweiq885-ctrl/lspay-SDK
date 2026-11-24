<?php
/**
 * 电子账户单笔提现查询示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\ElectronicAccountSingleWithdrawQueryRequest;

$request = (new ElectronicAccountSingleWithdrawQueryRequest())
    ->setAccountNo('2123214353453456564')
    ->setWithdrawDate('20250401')
    ->setOrderId('20250409123144123');

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "电子账户单笔提现查询失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo "提现状态: " . $result->getTransStatus() . "\n";
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
