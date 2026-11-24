<?php
/**
 * 企业电子账户开通示例。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\ElectronicAccountRelationTypeEnum;
use LsPaySdk\enums\ElectronicAccountTypeEnum;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\ElectronicAccountMerchantInfo;
use LsPaySdk\request\ElectronicAccountOpenChannelExtra;
use LsPaySdk\request\ElectronicAccountUserOpenRequest;

$merchantInfo = (new ElectronicAccountMerchantInfo())
    ->setRegName('企业全称')
    ->setShortName('企业简称')
    ->setLicenseCode('YOUR_LICENSE_CODE')
    ->setLicenseValidityType('0')
    ->setLicenseBeginDate('20200101')
    ->setLicenseEndDate('20400101')
    ->setRegProvId('310000')
    ->setRegAreaId('310100')
    ->setRegDistrictId('310101')
    ->setRegDetail('注册地址详情')
    ->setLegalName('法人姓名')
    ->setLegalCertType('00')
    ->setLegalCertNo('YOUR_LEGAL_CERT_NO')
    ->setLegalCertValidityType('0')
    ->setLegalCertBeginDate('20200101')
    ->setLegalCertEndDate('20400101')
    ->setContactName('联系人')
    ->setContactMobile('YOUR_CONTACT_MOBILE');

$channelExtra = (new ElectronicAccountOpenChannelExtra())
    ->setMerchantInfo($merchantInfo);

$request = (new ElectronicAccountUserOpenRequest())
    ->setOrderId('EACCOUNT' . date('YmdHis') . mt_rand(1000, 9999))
    ->setAccountName('企业全称')
    ->setAccountType(ElectronicAccountTypeEnum::ENTERPRISE)
    ->setRelationType(ElectronicAccountRelationTypeEnum::PARTNER)
    ->setAccountAlias('企业电子账户')
    ->setDivisionProfit('0')
    ->setClientIp('127.0.0.1')
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "企业电子账户开通失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
