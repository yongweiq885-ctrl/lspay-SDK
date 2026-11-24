<?php
/**
 * 电子账户结算记录查询示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\ElectronicAccountSettlementQueryRequest;

$request = (new ElectronicAccountSettlementQueryRequest())
    ->setOrderId('20160427210604000490')
    ->setAccountNo('2123214353453456564')
    ->setBeginDate('20250401')
    ->setEndDate('20250409')
    ->setPageSize(10)
    ->setPageNum(1);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "电子账户结算记录查询失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo "结算记录:\n";
    var_dump($result->getRecords());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
