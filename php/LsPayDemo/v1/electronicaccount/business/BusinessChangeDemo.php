<?php
/**
 * 电子账户业务变更示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\ElectronicAccountBusinessChangeChannelExtra;
use LsPaySdk\request\ElectronicAccountBusinessChangeRequest;
use LsPaySdk\request\ElectronicAccountCashConfig;
use LsPaySdk\request\ElectronicAccountModifiedCard;
use LsPaySdk\request\ElectronicAccountSettleConfig;

$channelExtra = (new ElectronicAccountBusinessChangeChannelExtra())
    ->setCardInfo(
        (new ElectronicAccountModifiedCard())
            ->setCardType('1')
            ->setCardName('持卡人姓名')
            ->setCardNo('YOUR_SETTLEMENT_CARD_NO')
    )
    ->setSettleConfig(
        (new ElectronicAccountSettleConfig())
            ->setSettleCycle('D1')
            ->setSettleStatus(1)
    )
    ->addCashConfig(
        (new ElectronicAccountCashConfig())
            ->setCashType('D1')
            ->setSwitchState(1)
            ->setOutFeeFlag(1)
    );

$request = (new ElectronicAccountBusinessChangeRequest())
    ->setOrderId('ECHANGE' . date('YmdHis') . mt_rand(1000, 9999))
    ->setAccountNo('YOUR_ELECTRONIC_ACCOUNT_NO')
    ->setClientIp('127.0.0.1')
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "电子账户业务变更失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
