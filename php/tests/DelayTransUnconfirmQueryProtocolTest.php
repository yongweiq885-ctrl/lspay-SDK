<?php
/**
 * 可转余额查询协议回归测试。
 *
 * 运行：php php/tests/DelayTransUnconfirmQueryProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\request\DelayTransUnconfirmQueryRequest;
use LsPaySdk\response\DelayTransUnconfirmQueryResponse;

final class DelayTransUnconfirmQueryTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"查询成功","data":{"unconfirm_amount":100}}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }
}

function balanceAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function balanceAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

LsPay::init([
    'mch_no' => 'TEST_MERCHANT',
    'app_id' => 'TEST_APP',
    'md5_key' => 'test-secret',
], true);

$tests = [];

$tests['endpoint and common-only payload'] = function () {
    $client = new DelayTransUnconfirmQueryTestClient();
    $response = $client->postRequest(new DelayTransUnconfirmQueryRequest());

    balanceAssertTrue(
        $response instanceof DelayTransUnconfirmQueryResponse,
        '应返回可转余额查询响应对象'
    );
    balanceAssertSame(
        'https://api.lspay.tech/gateway/delaytrans/unconfirm/query',
        $client->capturedUrl,
        '接口 URL'
    );
    balanceAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    $allowed = ['mch_no', 'app_id', 'req_time', 'version', 'sign_type', 'sign'];
    $unexpected = array_diff(array_keys($client->capturedData), $allowed);
    balanceAssertSame([], array_values($unexpected), '不得发送未定义业务参数');
};

$tests['typed response balance'] = function () {
    $client = new DelayTransUnconfirmQueryTestClient();
    $response = $client->postRequest(new DelayTransUnconfirmQueryRequest());

    balanceAssertTrue($response->isSuccessful(), 'status=200 应为查询成功');
    balanceAssertSame(100, $response->getUnconfirmAmount(), '可转余额应按分返回');
};

$tests['failed response'] = function () {
    $client = new DelayTransUnconfirmQueryTestClient();
    $client->responseBody = '{"status":400,"msg":"签名失败","data":{}}';
    $response = $client->postRequest(new DelayTransUnconfirmQueryRequest());

    balanceAssertTrue($response->isError(), '非 200 状态应为查询失败');
    balanceAssertTrue(!$response->isSuccessful(), '失败响应不得判断为成功');
    balanceAssertSame(null, $response->getUnconfirmAmount(), '失败响应无余额');
};

$tests['compatible array call'] = function () {
    $client = new DelayTransUnconfirmQueryTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::DELAY_TRANS_UNCONFIRM_QUERY,
        'params' => [],
    ]);

    balanceAssertTrue(
        $response instanceof DelayTransUnconfirmQueryResponse,
        '兼容数组调用也应返回类型化响应'
    );
    balanceAssertSame(100, $response->getUnconfirmAmount(), '兼容调用应解析余额');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Delay trans unconfirm query protocol: $passed/" . count($tests) . " passed\n";
