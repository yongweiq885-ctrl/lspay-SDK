<?php
/**
 * 电子账户业务开通协议回归测试。
 *
 * 运行：php php/tests/ElectronicAccountBusinessOpenProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\ElectronicAccountBusinessChannelExtra;
use LsPaySdk\request\ElectronicAccountBusinessOpenRequest;
use LsPaySdk\request\ElectronicAccountCashConfig;
use LsPaySdk\request\ElectronicAccountSettleConfig;
use LsPaySdk\request\ElectronicAccountSettlementCard;
use LsPaySdk\response\ElectronicAccountBusinessOpenResponse;

final class ElectronicAccountBusinessOpenTestClient extends LsPayClient
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

function privateSettlementCard()
{
    return (new ElectronicAccountSettlementCard())
        ->setCardType('1')
        ->setCardName('陈大海')
        ->setCardNo('0206014170008888')
        ->setProvId('100000')
        ->setAreaId('110000')
        ->setCertType('00')
        ->setCertNo('610621198404070414')
        ->setCertValidityType('0')
        ->setCertBeginDate('20170731')
        ->setCertEndDate('20370731')
        ->setMobile('13324502901');
}

function businessChannelExtra()
{
    return (new ElectronicAccountBusinessChannelExtra())
        ->setCardInfo(privateSettlementCard())
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
        )
        ->addCashConfig(
            (new ElectronicAccountCashConfig())
                ->setCashType('DM')
                ->setSwitchState(1)
                ->setOutFeeFlag(1)
        );
}

function businessOpenRequest()
{
    return (new ElectronicAccountBusinessOpenRequest())
        ->setOrderId('EBUSINESS1')
        ->setAccountNo('30160427210604000490')
        ->setClientIp('127.0.0.1')
        ->setChannelExtra(businessChannelExtra());
}

function businessAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function businessAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function businessAssertException($class, callable $callback, $message)
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

$tests['typed business configuration'] = function () {
    $request = businessOpenRequest();
    $request->validate();
    $extra = json_decode($request->getChannelExtra(), true);

    businessAssertSame('1', $extra['card_info']['card_type'], '对私结算卡');
    businessAssertSame('D1', $extra['settle_config']['settle_cycle'], '结算周期');
    businessAssertSame(2, count($extra['cash_config']), '提现配置数量');
    businessAssertTrue(
        strlen($request->getChannelExtra()) > 256,
        '完整业务配置应兼容超过文档标注的 256 字符'
    );
};

$tests['public settlement card rules'] = function () {
    $valid = (new ElectronicAccountSettlementCard())
        ->setCardType('0')
        ->setCardName('联速支付有限公司')
        ->setCardNo('0206014170008888')
        ->setProvId('100000')
        ->setAreaId('110000')
        ->setBankCode('01040000')
        ->setBranchCode('103124075619')
        ->setMobile('13324502901');
    $valid->toArray();

    foreach ([
        (clone $valid)->setBankCode(''),
        (clone $valid)->setBranchCode('')->setBranchName(''),
    ] as $invalid) {
        businessAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->toArray(); },
            '对公结算卡必须满足银行与支行规则'
        );
    }
};

$tests['private settlement card rules'] = function () {
    foreach ([
        privateSettlementCard()->setCertNo(''),
        privateSettlementCard()->setCertValidityType('0')->setCertEndDate(''),
        privateSettlementCard()->setCertBeginDate('20230230'),
        privateSettlementCard()->setProvId('1000'),
    ] as $invalid) {
        businessAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->toArray(); },
            '对私结算卡证件或地区格式非法时应拒绝'
        );
    }
};

$tests['settle and cash configuration rules'] = function () {
    businessAssertException(
        LsPayValidationException::class,
        function () {
            (new ElectronicAccountSettleConfig())
                ->setSettleCycle('T0')
                ->setSettleStatus(1)
                ->toArray();
        },
        '结算周期只允许 T1/D1'
    );
    foreach ([
        (new ElectronicAccountCashConfig())
            ->setCashType('INVALID')->setSwitchState(1)->setOutFeeFlag(1),
        (new ElectronicAccountCashConfig())
            ->setCashType('D1')->setSwitchState(3)->setOutFeeFlag(1),
        (new ElectronicAccountCashConfig())
            ->setCashType('D1')->setSwitchState(1)->setOutFeeFlag(3),
    ] as $invalid) {
        businessAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->toArray(); },
            '提现配置枚举非法时应拒绝'
        );
    }
};

$tests['request validation'] = function () {
    foreach ([
        new ElectronicAccountBusinessOpenRequest(),
        businessOpenRequest()->setOrderId(str_repeat('A', 31)),
        businessOpenRequest()->setAccountNo(str_repeat('A', 33)),
        businessOpenRequest()->setClientIp('999.1.1.1'),
        businessOpenRequest()->setChannelExtra(['card_info' => []]),
    ] as $invalid) {
        businessAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '业务开通必填或格式非法时应拒绝'
        );
    }
};

$tests['endpoint payload and response compatibility'] = function () {
    $client = new ElectronicAccountBusinessOpenTestClient();
    $response = $client->postRequest(businessOpenRequest());

    businessAssertTrue(
        $response instanceof ElectronicAccountBusinessOpenResponse,
        '应返回电子账户业务开通响应对象'
    );
    businessAssertSame(
        'https://api.lspay.tech/gateway/user/personconfirm',
        $client->capturedUrl,
        '接口 URL'
    );
    businessAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    businessAssertTrue($response->isSuccessful(), 'S 状态应为业务开通成功');

    $flat = new ElectronicAccountBusinessOpenResponse([
        'trans_status' => 'S',
        'account_no' => 'ACCOUNT1',
        'resp_desc' => '成功',
    ]);
    businessAssertTrue($flat->isSuccessful(), '平铺官方响应应兼容');
};

$tests['compatible array call uses validation'] = function () {
    $client = new ElectronicAccountBusinessOpenTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_OPEN,
        'params' => [
            'order_id' => 'EBUSINESS_ARRAY',
            'account_no' => 'ACCOUNT_ARRAY',
            'channel_extra' => businessChannelExtra()->toArray(),
        ],
    ]);
    businessAssertTrue(
        $response instanceof ElectronicAccountBusinessOpenResponse,
        '兼容数组调用也应返回类型化响应'
    );
};

$tests['settlement identity fields are redacted'] = function () {
    $client = new ElectronicAccountBusinessOpenTestClient();
    $safe = $client->sanitized([
        'account_no' => '30160427210604000490',
        'channel_extra' => businessChannelExtra()->toJson(),
    ]);
    $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE);
    foreach ([
        '30160427210604000490',
        '陈大海',
        '0206014170008888',
        '610621198404070414',
        '13324502901',
    ] as $secret) {
        businessAssertTrue(
            strpos($encoded, $secret) === false,
            "日志不得包含结算敏感值 $secret"
        );
    }
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Electronic account business open protocol: $passed/" . count($tests) . " passed\n";
