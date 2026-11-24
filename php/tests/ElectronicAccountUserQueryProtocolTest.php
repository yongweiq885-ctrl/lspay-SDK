<?php
/**
 * 电子账户用户查询协议回归测试。
 *
 * 运行：php php/tests/ElectronicAccountUserQueryProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\ElectronicAccountUserQueryRequest;
use LsPaySdk\response\ElectronicAccountUserQueryResponse;

final class ElectronicAccountUserQueryTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"成功","data":{"trans_status":"S","account_no":"35087342028002411688","account_name":"陈大海","channel_extra":"{\"settle_config\":{\"settle_cycle\":\"D1\"}}","channel_status":"1","resp_desc":"成功"}}';

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

function userQueryRequest()
{
    return (new ElectronicAccountUserQueryRequest())
        ->setAccountNo('35087342028002411688')
        ->setClientIp('127.0.0.1');
}

function userQueryAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function userQueryAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function userQueryAssertException($class, callable $callback, $message)
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

$tests['request validation'] = function () {
    foreach ([
        new ElectronicAccountUserQueryRequest(),
        userQueryRequest()->setAccountNo(str_repeat('A', 33)),
        userQueryRequest()->setClientIp('999.1.1.1'),
    ] as $invalid) {
        userQueryAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '用户查询必填或格式非法时应拒绝'
        );
    }
};

$tests['endpoint payload and typed response'] = function () {
    $client = new ElectronicAccountUserQueryTestClient();
    $response = $client->postRequest(userQueryRequest());

    userQueryAssertTrue(
        $response instanceof ElectronicAccountUserQueryResponse,
        '应返回电子账户用户查询响应对象'
    );
    userQueryAssertSame(
        'https://api.lspay.tech/gateway/user/query',
        $client->capturedUrl,
        '接口 URL'
    );
    userQueryAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    userQueryAssertSame('陈大海', $response->getAccountName(), '账户名称');
    userQueryAssertTrue($response->isOnboarded(), 'channel_status=1 应为入驻成功');
};

$tests['channel extra parsing'] = function () {
    $response = (new ElectronicAccountUserQueryTestClient())
        ->postRequest(userQueryRequest());
    userQueryAssertSame(
        'D1',
        $response->getChannelExtraArray()['settle_config']['settle_cycle'],
        'channel_extra JSON 字符串应可解析'
    );
};

$tests['channel status semantics'] = function () {
    foreach ([
        ['0', false],
        ['1', true],
        ['2', false],
        [0, false],
        [1, true],
        [2, false],
    ] as $case) {
        list($status, $expected) = $case;
        $response = new ElectronicAccountUserQueryResponse([
            'trans_status' => 'S',
            'account_no' => 'ACCOUNT1',
            'account_name' => '测试',
            'channel_extra' => [],
            'channel_status' => $status,
            'resp_desc' => '成功',
        ]);
        userQueryAssertSame($expected, $response->isOnboarded(), "channel_status=$status");
    }
};

$tests['compatible array call'] = function () {
    $client = new ElectronicAccountUserQueryTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::ELECTRONIC_ACCOUNT_USER_QUERY,
        'params' => ['account_no' => 'ACCOUNT_ARRAY'],
    ]);
    userQueryAssertTrue(
        $response instanceof ElectronicAccountUserQueryResponse,
        '兼容数组调用也应返回类型化响应'
    );
};

$tests['query identity fields are redacted'] = function () {
    $client = new ElectronicAccountUserQueryTestClient();
    $safe = $client->sanitized([
        'data' => [
            'account_no' => '35087342028002411688',
            'account_name' => '陈大海',
            'channel_extra' => '{"cert_no":"610621198404070414"}',
        ],
    ]);
    $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE);
    foreach (['35087342028002411688', '陈大海', '610621198404070414'] as $secret) {
        userQueryAssertTrue(
            strpos($encoded, $secret) === false,
            "日志不得包含用户查询敏感值 $secret"
        );
    }
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Electronic account user query protocol: $passed/" . count($tests) . " passed\n";
