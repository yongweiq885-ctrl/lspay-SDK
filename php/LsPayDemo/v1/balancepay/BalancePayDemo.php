<?php
/**
 * 余额支付示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\BalancePayChannelExtra;
use LsPaySdk\request\BalancePayRequest;
use LsPaySdk\request\DivisionReceiver;

$channelExtra = (new BalancePayChannelExtra())
    ->setTransOut('350873420280024268')
    ->addDivisionReceiver(
        (new DivisionReceiver('3709180455068368896'))->setDivAmt(100)
    )
    ->addDivisionReceiver(
        (new DivisionReceiver('3681144962280062976'))->setDivAmt(200)
    );

$request = (new BalancePayRequest())
    ->setMchOrderNo('8504920400284465')
    ->setAmount(300)
    ->setCurrency('cny')
    ->setSubject('余额转账测试')
    ->setBody('余额转账测试')
    ->setNotifyUrl('https://www.lspay.tech/lspay/notify')
    ->setReturnUrl('https://www.lspay.tech/success')
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "余额支付失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo $result->isPaid() ? "余额支付成功:\n" : "余额支付已受理:\n";
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
