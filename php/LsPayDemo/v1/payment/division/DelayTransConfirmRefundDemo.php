<?php
/**
 * 交易确认退款示例。
 *
 * 交易确认成功后，可将已确认金额退回原商户账户。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\DelayTransConfirmRefundRequest;

$request = (new DelayTransConfirmRefundRequest())
    ->setConfirmOrderId('YOUR_CONFIRM_ORDER_ID')
    ->setConfirmRefundNo('CREFUND' . date('YmdHis') . mt_rand(1000, 9999));

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "交易确认退款请求失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    if ($result->isSuccessful()) {
        echo "交易确认退款成功:\n";
    } else {
        echo "接口调用成功，但交易确认退款失败:\n";
    }
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
