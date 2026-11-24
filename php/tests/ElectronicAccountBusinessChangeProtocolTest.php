<?php
/**
 * 电子账户业务变更协议回归测试。
 *
 * 运行：php php/tests/ElectronicAccountBusinessChangeProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\ElectronicAccountBusinessChangeChannelExtra;
use LsPaySdk\request\ElectronicAccountBusinessChangeRequest;
use LsPaySdk\request\ElectronicAccountCashConfig;
use LsPaySdk\request\ElectronicAccountModifiedCard;
use LsPaySdk\request\ElectronicAccountSettleConfig;
use LsPaySdk\response\ElectronicAccountBusinessChangeResponse;

final class ElectronicAccountBusinessChangeTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"成功","data":{"trans_status":"S","resp_desc":"请求成功，业务处理中！","account_no":"3647174748412837888"}}';

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

function businessChangeExtra()
{
    return (new ElectronicAccountBusinessChangeChannelExtra())
        ->setCardInfo(
            (new ElectronicAccountModifiedCard())
                ->setCardType('1')
                ->setCardName('陈大海')
                ->setCardNo('621559370002034123123')
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
        )
        ->addCashConfig(
            (new ElectronicAccountCashConfig())
                ->setCashType('DM')
                ->setSwitchState(1)
                ->setOutFeeFlag(1)
        );
}

function businessChangeRequest()
{
    return (new ElectronicAccountBusinessChangeRequest())
        ->setOrderId('ECHANGE1')
        ->setAccountNo('3647174748412837888')
        ->setClientIp('127.0.0.1')
        ->setChannelExtra(businessChangeExtra());
}

function changeAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function changeAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function changeAssertException($class, callable $callback, $message)
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

$tests['typed change configuration'] = function () {
    $request = businessChangeRequest();
    $request->validate();
    $extra = json_decode($request->getChannelExtra(), true);

    changeAssertSame(
        ['card_type', 'card_name', 'card_no'],
        array_keys($extra['card_info']),
        '业务变更使用简化结算卡结构'
    );
    changeAssertSame(2, count($extra['cash_config']), '提现配置数量');
    changeAssertTrue(
        strlen($request->getChannelExtra()) > 256,
        '完整变更配置应兼容超过 256 字符'
    );
};

$tests['modified card rules'] = function () {
    foreach ([
        (new ElectronicAccountModifiedCard())
            ->setCardName('陈大海')->setCardNo('CARD1'),
        (new ElectronicAccountModifiedCard())
            ->setCardType('1')->setCardNo('CARD1'),
        (new ElectronicAccountModifiedCard())
            ->setCardType('1')->setCardName('陈大海'),
        (new ElectronicAccountModifiedCard())
            ->setCardType('3')->setCardName('陈大海')->setCardNo('CARD1'),
    ] as $invalid) {
        changeAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->toArray(); },
            '业务变更结算卡必填或枚举非法时应拒绝'
        );
    }
};

$tests['configuration sections required'] = function () {
    $card = (new ElectronicAccountModifiedCard())
        ->setCardType('1')->setCardName('陈大海')->setCardNo('CARD1');
    $settle = (new ElectronicAccountSettleConfig())
        ->setSettleCycle('D1')->setSettleStatus(1);

    foreach ([
        (new ElectronicAccountBusinessChangeChannelExtra())
            ->setSettleConfig($settle)
            ->addCashConfig(
                (new ElectronicAccountCashConfig())
                    ->setCashType('D1')->setSwitchState(1)->setOutFeeFlag(1)
            ),
        (new ElectronicAccountBusinessChangeChannelExtra())
            ->setCardInfo($card)
            ->addCashConfig(
                (new ElectronicAccountCashConfig())
                    ->setCashType('D1')->setSwitchState(1)->setOutFeeFlag(1)
            ),
        (new ElectronicAccountBusinessChangeChannelExtra())
            ->setCardInfo($card)
            ->setSettleConfig($settle),
    ] as $invalid) {
        changeAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->toArray(); },
            '三个业务配置分区均为必填'
        );
    }
};

$tests['request validation'] = function () {
    foreach ([
        new ElectronicAccountBusinessChangeRequest(),
        businessChangeRequest()->setOrderId(str_repeat('A', 31)),
        businessChangeRequest()->setAccountNo(str_repeat('A', 33)),
        businessChangeRequest()->setClientIp('999.1.1.1'),
        businessChangeRequest()->setChannelExtra(['card_info' => []]),
    ] as $invalid) {
        changeAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '业务变更请求必填或格式非法时应拒绝'
        );
    }
};

$tests['endpoint payload and response compatibility'] = function () {
    $client = new ElectronicAccountBusinessChangeTestClient();
    $response = $client->postRequest(businessChangeRequest());

    changeAssertTrue(
        $response instanceof ElectronicAccountBusinessChangeResponse,
        '应返回电子账户业务变更响应对象'
    );
    changeAssertSame(
        'https://api.lspay.tech/gateway/user/personmodify',
        $client->capturedUrl,
        '接口 URL'
    );
    changeAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    changeAssertTrue($response->isSuccessful(), 'S 状态应为变更请求成功');
    changeAssertSame(
        '请求成功，业务处理中！',
        $response->getRespDesc(),
        '响应描述'
    );

    $flat = new ElectronicAccountBusinessChangeResponse([
        'trans_status' => 'S',
        'account_no' => 'ACCOUNT1',
        'resp_desc' => '成功',
    ]);
    changeAssertTrue($flat->isSuccessful(), '平铺官方响应应兼容');
};

$tests['compatible array call uses validation'] = function () {
    $client = new ElectronicAccountBusinessChangeTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_CHANGE,
        'params' => [
            'order_id' => 'ECHANGE_ARRAY',
            'account_no' => 'ACCOUNT_ARRAY',
            'channel_extra' => businessChangeExtra()->toArray(),
        ],
    ]);
    changeAssertTrue(
        $response instanceof ElectronicAccountBusinessChangeResponse,
        '兼容数组调用也应返回类型化响应'
    );
};

$tests['change identity fields are redacted'] = function () {
    $client = new ElectronicAccountBusinessChangeTestClient();
    $safe = $client->sanitized([
        'account_no' => '3647174748412837888',
        'channel_extra' => businessChangeExtra()->toJson(),
    ]);
    $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE);
    foreach (['3647174748412837888', '陈大海', '621559370002034123123'] as $secret) {
        changeAssertTrue(
            strpos($encoded, $secret) === false,
            "日志不得包含业务变更敏感值 $secret"
        );
    }
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Electronic account business change protocol: $passed/" . count($tests) . " passed\n";
