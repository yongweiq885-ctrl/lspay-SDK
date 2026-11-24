<?php
/**
 * HTTP 传输与协议错误回归测试。
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayNetworkException;
use LsPaySdk\exceptions\LsPayProtocolException;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\QueryOrderRequest;

final class TransportTestClient extends LsPayClient
{
    public $responseBody = '{"status":200,"msg":"成功","data":{"order_state":0}}';

    protected function curlRequest($url, array $data)
    {
        return $this->responseBody;
    }

    public function option($name)
    {
        $property = new ReflectionProperty(\LsPaySdk\core\LsPay::class, $name);
        $property->setAccessible(true);
        return $property->getValue($this);
    }
}

function transportAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function transportAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

function transportAssertThrows($class, callable $callback, $message)
{
    try {
        $callback();
    } catch (\Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new RuntimeException($message . ': ' . get_class($e));
    }
    throw new RuntimeException($message);
}

LsPay::init([
    'mch_no' => 'TEST_MERCHANT',
    'app_id' => 'TEST_APP',
    'md5_key' => 'test-secret',
], true);

$tests = [];

$tests['transport options'] = function () {
    $client = (new TransportTestClient())
        ->setConnectTimeout(3)
        ->setRequestTimeout(12)
        ->setUserAgent('MerchantApp/1.0')
        ->setProxy('127.0.0.1:8080');
    transportAssertSame(3, $client->option('connectTimeout'), '连接超时');
    transportAssertSame(12, $client->option('requestTimeout'), '请求超时');
    transportAssertSame('MerchantApp/1.0', $client->option('userAgent'), 'User-Agent');
    transportAssertSame('127.0.0.1:8080', $client->option('proxy'), '代理');
};

$tests['invalid transport options'] = function () {
    foreach ([
        function () { (new TransportTestClient())->setConnectTimeout(0); },
        function () { (new TransportTestClient())->setRequestTimeout('1.5'); },
        function () { (new TransportTestClient())->setUserAgent(''); },
    ] as $callback) {
        transportAssertThrows(
            LsPayValidationException::class,
            $callback,
            '非法传输参数应拒绝'
        );
    }
};

$tests['invalid json is protocol exception'] = function () {
    $client = new TransportTestClient();
    $client->responseBody = '<html>upstream error</html>';
    transportAssertThrows(
        LsPayProtocolException::class,
        function () use ($client) {
            $client->postRequest(
                (new QueryOrderRequest())->setMchOrderNo('ORDER1')
            );
        },
        '非 JSON 响应应抛协议异常'
    );
};

$tests['structured network exception'] = function () {
    $exception = new LsPayNetworkException('HTTP error', 502, 0, '[omitted]');
    transportAssertSame(502, $exception->httpStatus, 'HTTP 状态');
    transportAssertSame('[omitted]', $exception->responseDetail, '脱敏响应');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Transport protocol: $passed/" . count($tests) . " passed\n";
