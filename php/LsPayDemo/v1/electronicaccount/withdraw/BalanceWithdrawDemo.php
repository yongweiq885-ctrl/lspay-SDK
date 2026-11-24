<?php
/**
 * 电子账户余额提现示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\ElectronicAccountBalanceWithdrawRequest;

$request = (new ElectronicAccountBalanceWithdrawRequest())
    ->setOrderId('20160427210604000490')
    ->setAccountNo('2123214353453456564')
    ->setWithdrawAmount('99.99');

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "电子账户余额提现失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo $result->isAccepted() ? "提现已受理:\n" : "提现未受理:\n";
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
