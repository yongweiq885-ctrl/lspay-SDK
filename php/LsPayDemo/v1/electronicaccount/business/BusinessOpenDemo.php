<?php
/**
 * 电子账户业务开通示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\ElectronicAccountBusinessChannelExtra;
use LsPaySdk\request\ElectronicAccountBusinessOpenRequest;
use LsPaySdk\request\ElectronicAccountCashConfig;
use LsPaySdk\request\ElectronicAccountSettleConfig;
use LsPaySdk\request\ElectronicAccountSettlementCard;

$card = (new ElectronicAccountSettlementCard())
    ->setCardType('1')
    ->setCardName('持卡人姓名')
    ->setCardNo('YOUR_SETTLEMENT_CARD_NO')
    ->setProvId('100000')
    ->setAreaId('110000')
    ->setCertType('00')
    ->setCertNo('YOUR_CERTIFICATE_NO')
    ->setCertValidityType('0')
    ->setCertBeginDate('20200101')
    ->setCertEndDate('20400101')
    ->setMobile('YOUR_BANK_MOBILE');

$settle = (new ElectronicAccountSettleConfig())
    ->setSettleCycle('D1')
    ->setSettleStatus(1);

$channelExtra = (new ElectronicAccountBusinessChannelExtra())
    ->setCardInfo($card)
    ->setSettleConfig($settle)
    ->addCashConfig(
        (new ElectronicAccountCashConfig())
            ->setCashType('D1')
            ->setSwitchState(1)
            ->setOutFeeFlag(1)
    );

$request = (new ElectronicAccountBusinessOpenRequest())
    ->setOrderId('EBUSINESS' . date('YmdHis') . mt_rand(1000, 9999))
    ->setAccountNo('YOUR_ELECTRONIC_ACCOUNT_NO')
    ->setClientIp('127.0.0.1')
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "电子账户业务开通失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo $result->isSuccessful()
        ? "电子账户业务开通成功:\n"
        : "电子账户业务开通尚未完成:\n";
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
