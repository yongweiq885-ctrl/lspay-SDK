<?php
/**
 * 电子账户单笔提现查询协议回归测试。
 *
 * 运行：php php/tests/ElectronicAccountSingleWithdrawQueryProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\ElectronicAccountSingleWithdrawQueryRequest;
use LsPaySdk\response\ElectronicAccountSingleWithdrawQueryResponse;

final class ElectronicAccountSingleWithdrawQueryTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"success","data":{"cash_amt":"1.00","fee_amt":"0.00","withdraw_date":"20260609","order_id":"1111111111","trans_desc":"成功","trans_status":"succeeded","status":"succeess"}}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }
}

function singleWithdrawQueryRequest()
{
    return (new ElectronicAccountSingleWithdrawQueryRequest())
        ->setAccountNo('2123214353453456564')
        ->setWithdrawDate('20250401')
        ->setOrderId('20250409123144123');
}

function singleWithdrawQueryAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function singleWithdrawQueryAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function singleWithdrawQueryAssertException($class, callable $callback, $message)
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
    singleWithdrawQueryAssertSame(
        'WITHDRAW_SINGLE_QUERY',
        singleWithdrawQueryRequest()->getWayCode(),
        'way_code 默认固定值'
    );
    foreach ([
        new ElectronicAccountSingleWithdrawQueryRequest(),
        singleWithdrawQueryRequest()->setWayCode('OTHER'),
        singleWithdrawQueryRequest()->setWithdrawDate('20250230'),
        singleWithdrawQueryRequest()->setOrderId(str_repeat('A', 31)),
        singleWithdrawQueryRequest()->setAccountNo(str_repeat('A', 33)),
    ] as $invalid) {
        singleWithdrawQueryAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '单笔提现查询必填或格式非法时应拒绝'
        );
    }
};

$tests['endpoint payload and typed response'] = function () {
    $client = new ElectronicAccountSingleWithdrawQueryTestClient();
    $response = $client->postRequest(singleWithdrawQueryRequest());

    singleWithdrawQueryAssertTrue(
        $response instanceof ElectronicAccountSingleWithdrawQueryResponse,
        '应返回单笔提现查询响应对象'
    );
    singleWithdrawQueryAssertSame(
        'https://api.lspay.tech/gateway/user/balance/withdraw/single/query',
        $client->capturedUrl,
        '接口 URL'
    );
    singleWithdrawQueryAssertSame(
        'WITHDRAW_SINGLE_QUERY',
        $client->capturedData['way_code'],
        '支付方式'
    );
    singleWithdrawQueryAssertSame('20250401', $client->capturedData['withdraw_date'], '提现日期');
    singleWithdrawQueryAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    singleWithdrawQueryAssertSame('1.00', $response->getCashAmount(), '提现金额');
    singleWithdrawQueryAssertSame('succeess', $response->getChannelStatus(), '渠道状态原样返回');
    singleWithdrawQueryAssertTrue($response->isChannelSuccessful(), '识别接口特有 succeess 成功状态');
    singleWithdrawQueryAssertTrue($response->isSucceeded(), 'succeeded 应标记为成功');
};

$tests['withdraw status semantics'] = function () {
    foreach ([
        'pending' => 'isPending',
        'failed' => 'isFailed',
        'succeeded' => 'isSucceeded',
    ] as $status => $method) {
        $response = new ElectronicAccountSingleWithdrawQueryResponse([
            'status' => 200,
            'msg' => 'success',
            'data' => ['trans_status' => $status],
        ]);
        singleWithdrawQueryAssertTrue($response->$method(), "$status 状态判断");
    }
    $corrected = new ElectronicAccountSingleWithdrawQueryResponse([
        'status' => 200,
        'data' => ['status' => 'success'],
    ]);
    singleWithdrawQueryAssertTrue($corrected->isChannelSuccessful(), '兼容相关接口 success 成功状态');
};

$tests['gateway failure'] = function () {
    $client = new ElectronicAccountSingleWithdrawQueryTestClient();
    $client->responseBody = '{"status":400,"msg":"参数错误","data":[]}';
    $response = $client->postRequest(singleWithdrawQueryRequest());
    singleWithdrawQueryAssertTrue($response->isError(), 'status=400 应为失败');
    singleWithdrawQueryAssertTrue(!$response->isSucceeded(), '网关失败不应标记为提现成功');
};

$tests['compatible array call'] = function () {
    $client = new ElectronicAccountSingleWithdrawQueryTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY,
        'params' => [
            'account_no' => 'ACCOUNT_ARRAY',
            'withdraw_date' => '20250401',
            'order_id' => 'WITHDRAW_ARRAY_1',
        ],
    ]);
    singleWithdrawQueryAssertTrue(
        $response instanceof ElectronicAccountSingleWithdrawQueryResponse,
        '兼容数组调用也应返回类型化响应'
    );
    singleWithdrawQueryAssertSame(
        'WITHDRAW_SINGLE_QUERY',
        $client->capturedData['way_code'],
        '数组调用应补齐固定 way_code'
    );
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Electronic account single withdraw query protocol: $passed/"
    . count($tests) . " passed\n";
