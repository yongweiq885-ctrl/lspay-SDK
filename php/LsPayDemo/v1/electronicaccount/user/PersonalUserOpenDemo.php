<?php
/**
 * 个人电子账户开通示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\ElectronicAccountRelationTypeEnum;
use LsPaySdk\enums\ElectronicAccountTypeEnum;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\ElectronicAccountOpenChannelExtra;
use LsPaySdk\request\ElectronicAccountUserInfo;
use LsPaySdk\request\ElectronicAccountUserOpenRequest;

$userInfo = (new ElectronicAccountUserInfo())
    ->setCertType('00')
    ->setName('开户姓名')
    ->setCertNo('YOUR_CERTIFICATE_NO')
    ->setCertValidityType('0')
    ->setCertBeginDate('20200101')
    ->setCertEndDate('20400101')
    ->setMobileNo('YOUR_MOBILE')
    ->setEmail('user@example.com');

$channelExtra = (new ElectronicAccountOpenChannelExtra())
    ->setUserInfo($userInfo);

$request = (new ElectronicAccountUserOpenRequest())
    ->setOrderId('EACCOUNT' . date('YmdHis') . mt_rand(1000, 9999))
    ->setAccountName('开户姓名')
    ->setAccountType(ElectronicAccountTypeEnum::PERSONAL)
    ->setRelationType(ElectronicAccountRelationTypeEnum::USER)
    ->setAccountAlias('个人电子账户')
    ->setDivisionProfit('0.0410')
    ->setClientIp('127.0.0.1')
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "电子账户开通失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo $result->hasAccount()
        ? "电子账户开通成功，请安全保存 account_no:\n"
        : "电子账户开通尚未完成:\n";
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
