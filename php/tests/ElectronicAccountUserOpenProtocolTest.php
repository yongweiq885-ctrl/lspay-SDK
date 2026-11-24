<?php
/**
 * 电子账户用户开通协议回归测试。
 *
 * 运行：php php/tests/ElectronicAccountUserOpenProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\ElectronicAccountRelationTypeEnum;
use LsPaySdk\enums\ElectronicAccountTypeEnum;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\ElectronicAccountMerchantInfo;
use LsPaySdk\request\ElectronicAccountOpenChannelExtra;
use LsPaySdk\request\ElectronicAccountUserInfo;
use LsPaySdk\request\ElectronicAccountUserOpenRequest;
use LsPaySdk\response\ElectronicAccountUserOpenResponse;

final class ElectronicAccountUserOpenTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"成功","data":{"trans_status":"S","account_no":"30160427210604000490","resp_desc":"成功"}}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }

    public function sanitized(array $data)
    {
        return $this->sanitizeForLog($data);
    }
}

function personalUserInfo()
{
    return (new ElectronicAccountUserInfo())
        ->setCertType('00')
        ->setName('陈大海')
        ->setCertNo('610621198404070414')
        ->setCertValidityType('0')
        ->setCertBeginDate('20170731')
        ->setCertEndDate('20370731')
        ->setMobileNo('13324502901')
        ->setEmail('user@example.com');
}

function enterpriseMerchantInfo()
{
    return (new ElectronicAccountMerchantInfo())
        ->setRegName('联速支付有限公司')
        ->setShortName('南宁联速')
        ->setLicenseCode('92650109MA79R8E308')
        ->setLicenseValidityType('0')
        ->setLicenseBeginDate('20220905')
        ->setLicenseEndDate('20320905')
        ->setRegProvId('310000')
        ->setRegAreaId('310100')
        ->setRegDistrictId('010101')
        ->setRegDetail('南宁')
        ->setLegalName('张三')
        ->setLegalCertType('00')
        ->setLegalCertNo('320906197412025012')
        ->setLegalCertValidityType('0')
        ->setLegalCertBeginDate('20220905')
        ->setLegalCertEndDate('20320905')
        ->setContactName('李四')
        ->setContactMobile('13311111111');
}

function personalOpenRequest()
{
    return (new ElectronicAccountUserOpenRequest())
        ->setOrderId('EACCOUNT1')
        ->setAccountName('陈大海')
        ->setAccountType(ElectronicAccountTypeEnum::PERSONAL)
        ->setRelationType(ElectronicAccountRelationTypeEnum::USER)
        ->setAccountAlias('个人账户')
        ->setDivisionProfit('0.0410')
        ->setClientIp('127.0.0.1')
        ->setChannelExtra(
            (new ElectronicAccountOpenChannelExtra())
                ->setUserInfo(personalUserInfo())
        );
}

function accountAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function accountAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function accountAssertException($class, callable $callback, $message)
{
    try {
        $callback();
    } catch (\Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new RuntimeException(
            $message . '; exception=' . get_class($e) . ': ' . $e->getMessage()
        );
    }
    throw new RuntimeException($message);
}

LsPay::init([
    'mch_no' => 'TEST_MERCHANT',
    'app_id' => 'TEST_APP',
    'md5_key' => 'test-secret',
], true);

$tests = [];

$tests['personal account channel extra'] = function () {
    $request = personalOpenRequest();
    $request->validate();
    $extra = json_decode($request->getChannelExtra(), true);

    accountAssertSame('00', $extra['user_info']['cert_type'], '默认证件类型');
    accountAssertSame('user@example.com', $extra['user_info']['email'], '电子邮箱');
    accountAssertSame(WayCodeEnum::APPLY, $request->getWayCode(), '固定接口代码');
};

$tests['enterprise account channel extra supports documented example length'] = function () {
    $extra = (new ElectronicAccountOpenChannelExtra())
        ->setMerchantInfo(enterpriseMerchantInfo());
    accountAssertTrue(
        strlen($extra->toJson()) > 256,
        '企业官方字段集合应能覆盖超过 256 字符的文档示例'
    );

    $request = (new ElectronicAccountUserOpenRequest())
        ->setOrderId('EACCOUNT2')
        ->setAccountName('联速支付有限公司')
        ->setAccountType(ElectronicAccountTypeEnum::ENTERPRISE)
        ->setRelationType(ElectronicAccountRelationTypeEnum::PARTNER)
        ->setAccountAlias('企业账户')
        ->setDivisionProfit('0')
        ->setChannelExtra($extra);
    $request->validate();
};

$tests['required enums and custom relation'] = function () {
    foreach ([
        new ElectronicAccountUserOpenRequest(),
        personalOpenRequest()->setAccountType(4),
        personalOpenRequest()->setRelationType('UNKNOWN'),
        personalOpenRequest()
            ->setRelationType(ElectronicAccountRelationTypeEnum::CUSTOM)
            ->setRelationTypeName(''),
        personalOpenRequest()->setClientIp('999.1.1.1'),
    ] as $invalid) {
        accountAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '必填、枚举或条件字段非法时应拒绝'
        );
    }
    accountAssertException(
        LsPayValidationException::class,
        function () { personalOpenRequest()->setWayCode('UNKNOWN'); },
        'way_code 只能是 APPLY'
    );
};

$tests['division profit validation'] = function () {
    foreach (['0', '0.0410', '1', '1.0000'] as $ratio) {
        personalOpenRequest()->setDivisionProfit($ratio)->validate();
    }
    foreach (['-0.1', '1.1', 'abc'] as $ratio) {
        $invalid = personalOpenRequest()->setDivisionProfit($ratio);
        accountAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '分账比例必须在 0 到 1 之间'
        );
    }
};

$tests['account type must match nested info'] = function () {
    $personalWithMerchant = personalOpenRequest()
        ->setChannelExtra(
            (new ElectronicAccountOpenChannelExtra())
                ->setMerchantInfo(enterpriseMerchantInfo())
        );
    accountAssertException(
        LsPayValidationException::class,
        function () use ($personalWithMerchant) { $personalWithMerchant->validate(); },
        '个人账户必须传 user_info'
    );

    $enterpriseWithUser = personalOpenRequest()
        ->setAccountType(ElectronicAccountTypeEnum::ENTERPRISE)
        ->setChannelExtra(
            (new ElectronicAccountOpenChannelExtra())
                ->setUserInfo(personalUserInfo())
        );
    accountAssertException(
        LsPayValidationException::class,
        function () use ($enterpriseWithUser) { $enterpriseWithUser->validate(); },
        '企业账户必须传 mer_info'
    );
};

$tests['endpoint payload and response compatibility'] = function () {
    $client = new ElectronicAccountUserOpenTestClient();
    $response = $client->postRequest(personalOpenRequest());

    accountAssertTrue(
        $response instanceof ElectronicAccountUserOpenResponse,
        '应返回电子账户用户开通响应对象'
    );
    accountAssertSame(
        'https://api.lspay.tech/gateway/user/personapply',
        $client->capturedUrl,
        '接口 URL'
    );
    accountAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    accountAssertTrue($response->hasAccount(), '成功响应应包含电子账户编号');

    $flat = new ElectronicAccountUserOpenResponse([
        'trans_status' => 'S',
        'account_no' => 'ACCOUNT1',
        'resp_desc' => '成功',
    ]);
    accountAssertTrue($flat->hasAccount(), '平铺官方响应应兼容');
};

$tests['compatible array call uses validation'] = function () {
    $client = new ElectronicAccountUserOpenTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::ELECTRONIC_ACCOUNT_USER_OPEN,
        'params' => [
            'way_code' => WayCodeEnum::APPLY,
            'order_id' => 'EACCOUNT_ARRAY',
            'account_name' => '陈大海',
            'account_type' => 1,
            'relation_type' => 'USER',
            'account_alias' => '个人账户',
            'division_profit' => '0',
            'channel_extra' => [
                'user_info' => personalUserInfo()->toArray(),
            ],
        ],
    ]);
    accountAssertTrue(
        $response instanceof ElectronicAccountUserOpenResponse,
        '兼容数组调用也应返回类型化响应'
    );
};

$tests['account identity fields are redacted'] = function () {
    $client = new ElectronicAccountUserOpenTestClient();
    $safe = $client->sanitized([
        'account_name' => '陈大海',
        'account_alias' => '个人账户',
        'channel_extra' => (new ElectronicAccountOpenChannelExtra())
            ->setUserInfo(personalUserInfo())
            ->toJson(),
        'data' => ['account_no' => '30160427210604000490'],
    ]);
    $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE);
    foreach ([
        '陈大海',
        '个人账户',
        '610621198404070414',
        '13324502901',
        'user@example.com',
        '30160427210604000490',
    ] as $secret) {
        accountAssertTrue(
            strpos($encoded, $secret) === false,
            "日志不得包含电子账户敏感值 $secret"
        );
    }
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Electronic account user open protocol: $passed/" . count($tests) . " passed\n";
