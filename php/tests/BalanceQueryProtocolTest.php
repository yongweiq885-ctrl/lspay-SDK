<?php
/**
 * 余额查询协议回归测试。
 *
 * 运行：php php/tests/BalanceQueryProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\BalanceQueryRequest;
use LsPaySdk\response\BalanceQueryResponse;

final class BalanceQueryTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"acct_type":"01","acct_stat":"N","balance_amt":"1","avl_balance":"1","frz_bal":"1","dm_amt":"0.00"}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }
}

function balanceQueryRequest()
{
    return (new BalanceQueryRequest())
        ->setOrderId('20160427210604000490')
        ->setAccountNo('313920933111042')
        ->setClientIp('210.73.10.148');
}

function balanceQueryAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function balanceQueryAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function balanceQueryAssertException($class, callable $callback, $message)
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

$tests['fixed fields and request validation'] = function () {
    balanceQueryAssertSame('BALANCE_QUERY', balanceQueryRequest()->getWayCode(), '固定 way_code');
    foreach ([
        new BalanceQueryRequest(),
        balanceQueryRequest()->setWayCode('OTHER'),
        balanceQueryRequest()->setOrderId(str_repeat('A', 31)),
        balanceQueryRequest()->setAccountNo(str_repeat('A', 257)),
        balanceQueryRequest()->setClientIp('999.1.1.1'),
    ] as $invalid) {
        balanceQueryAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '余额查询必填或格式非法时应拒绝'
        );
    }
};

$tests['endpoint payload and flat response'] = function () {
    $client = new BalanceQueryTestClient();
    $response = $client->postRequest(balanceQueryRequest());

    balanceQueryAssertTrue($response instanceof BalanceQueryResponse, '应返回余额查询响应对象');
    balanceQueryAssertSame(
        'https://api.lspay.tech/gateway/user/balance/query',
        $client->capturedUrl,
        '接口 URL'
    );
    balanceQueryAssertSame('BALANCE_QUERY', $client->capturedData['way_code'], '接口代码');
    balanceQueryAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    balanceQueryAssertTrue(!$response->isError(), '文档扁平响应应识别为成功');
    balanceQueryAssertTrue($response->isNormal(), 'acct_stat=N 应为正常');
    balanceQueryAssertSame('1', $response->getAvailableBalance(), '可用余额');
    balanceQueryAssertSame('0.00', $response->getHistoricalBalance(), '历史余额');
    balanceQueryAssertSame('01', $response->getData()['acct_type'], '扁平响应应作为业务数据');
};

$tests['wrapped response compatibility'] = function () {
    $client = new BalanceQueryTestClient();
    $client->responseBody = '{"status":200,"msg":"成功","data":{"acct_type":"01","acct_stat":"F","balance_amt":"10.00","avl_balance":"0.00","frz_bal":"10.00","dm_amt":"0.00"}}';
    $response = $client->postRequest(balanceQueryRequest());
    balanceQueryAssertTrue(!$response->isError(), '包装响应应识别为成功');
    balanceQueryAssertTrue(!$response->isNormal(), 'acct_stat=F 不应为正常');
    balanceQueryAssertSame('10.00', $response->getFrozenBalance(), '冻结金额');
};

$tests['gateway failure'] = function () {
    $client = new BalanceQueryTestClient();
    $client->responseBody = '{"status":400,"msg":"参数错误","data":[]}';
    $response = $client->postRequest(balanceQueryRequest());
    balanceQueryAssertTrue($response->isError(), 'status=400 应为失败');
};

$tests['compatible array call'] = function () {
    $client = new BalanceQueryTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::BALANCE_QUERY,
        'params' => [
            'order_id' => 'BALANCE_QUERY_ARRAY_1',
            'account_no' => 'ACCOUNT_ARRAY',
        ],
    ]);
    balanceQueryAssertTrue($response instanceof BalanceQueryResponse, '兼容数组调用应返回类型化响应');
    balanceQueryAssertSame('BALANCE_QUERY', $client->capturedData['way_code'], '数组调用补齐固定 way_code');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Balance query protocol: $passed/" . count($tests) . " passed\n";
