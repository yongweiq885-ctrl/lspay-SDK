<?php
/**
 * 延时分账交易确认示例。
 *
 * 请将支付订单号及分账接收方替换为真实业务数据后调用。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\DelayTransConfirmRequest;
use LsPaySdk\request\DivisionChannelExtra;
use LsPaySdk\request\DivisionReceiver;

$channelExtra = (new DivisionChannelExtra())
    ->addDivisionReceiver(
        (new DivisionReceiver('YOUR_RECEIVER_USER_ID'))
            ->setDivAmt(10)
            ->setDescription('经销商')
    )
    ->addDivisionReceiver(
        (new DivisionReceiver('YOUR_SECOND_RECEIVER_USER_ID'))
            ->setDivAmt(10)
            ->setDescription('渠道商')
    );

$request = (new DelayTransConfirmRequest())
    ->setPayOrderId('YOUR_PAY_ORDER_ID')
    ->setOrderId('CONFIRM' . date('YmdHis') . mt_rand(1000, 9999))
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "延时分账交易确认请求失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    if ($result->isSuccessful()) {
        echo "交易确认成功:\n";
    } else {
        echo "接口调用成功，但交易确认失败:\n";
    }
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
