<?php
/**
 * 电子账户余额提现协议回归测试。
 *
 * 运行：php php/tests/ElectronicAccountBalanceWithdrawProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\ElectronicAccountBalanceWithdrawRequest;
use LsPaySdk\response\ElectronicAccountBalanceWithdrawResponse;

final class ElectronicAccountBalanceWithdrawTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"success","data":{"err_msg":"受理成功","status":"success"}}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }
}

function balanceWithdrawRequest()
{
    return (new ElectronicAccountBalanceWithdrawRequest())
        ->setOrderId('20160427210604000490')
        ->setAccountNo('2123214353453456564')
        ->setWithdrawAmount('99.99');
}

function balanceWithdrawAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function balanceWithdrawAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function balanceWithdrawAssertException($class, callable $callback, $message)
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

$tests['fixed fields and amount validation'] = function () {
    balanceWithdrawAssertSame(
        'BALANCE_WITHDRAW',
        balanceWithdrawRequest()->getWayCode(),
        'way_code 默认固定值'
    );
    foreach ([
        new ElectronicAccountBalanceWithdrawRequest(),
        balanceWithdrawRequest()->setWayCode('OTHER'),
        balanceWithdrawRequest()->setWithdrawAmount('0'),
        balanceWithdrawRequest()->setWithdrawAmount('-1'),
        balanceWithdrawRequest()->setWithdrawAmount('01.00'),
        balanceWithdrawRequest()->setWithdrawAmount('1.001'),
        balanceWithdrawRequest()->setWithdrawAmount('1e2'),
    ] as $invalid) {
        balanceWithdrawAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '余额提现必填或金额非法时应拒绝'
        );
    }
    balanceWithdrawRequest()->setWithdrawAmount('0.01')->validate();
};

$tests['endpoint payload and accepted response'] = function () {
    $client = new ElectronicAccountBalanceWithdrawTestClient();
    $response = $client->postRequest(balanceWithdrawRequest());

    balanceWithdrawAssertTrue(
        $response instanceof ElectronicAccountBalanceWithdrawResponse,
        '应返回余额提现响应对象'
    );
    balanceWithdrawAssertSame(
        'https://api.lspay.tech/gateway/user/balance/withdraw',
        $client->capturedUrl,
        '接口 URL'
    );
    balanceWithdrawAssertSame(
        'BALANCE_WITHDRAW',
        $client->capturedData['way_code'],
        '支付方式'
    );
    balanceWithdrawAssertSame('99.99', $client->capturedData['withdraw_amount'], '提现金额');
    balanceWithdrawAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    balanceWithdrawAssertSame('受理成功', $response->getAcceptanceMessage(), '受理信息');
    balanceWithdrawAssertTrue($response->isAccepted(), 'success 应标记为已受理');
    $specific = new ElectronicAccountBalanceWithdrawResponse([
        'status' => 200,
        'data' => ['status' => 'succeess'],
    ]);
    balanceWithdrawAssertTrue($specific->isAccepted(), 'succeess 特有状态应标记为已受理');
};

$tests['failed response'] = function () {
    $client = new ElectronicAccountBalanceWithdrawTestClient();
    $client->responseBody = '{"status":400,"msg":"渠道侧:暂未开通取现权限","data":[]}';
    $response = $client->postRequest(balanceWithdrawRequest());
    balanceWithdrawAssertTrue($response->isError(), 'status=400 应为失败');
    balanceWithdrawAssertTrue(!$response->isAccepted(), '失败响应不应标记为已受理');
    balanceWithdrawAssertSame([], $response->getData(), '空数组 data 应保留');
};

$tests['compatible array call'] = function () {
    $client = new ElectronicAccountBalanceWithdrawTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW,
        'params' => [
            'order_id' => 'WITHDRAW_ARRAY_1',
            'account_no' => 'ACCOUNT_ARRAY',
            'withdraw_amount' => '8.88',
        ],
    ]);
    balanceWithdrawAssertTrue(
        $response instanceof ElectronicAccountBalanceWithdrawResponse,
        '兼容数组调用也应返回类型化响应'
    );
    balanceWithdrawAssertSame(
        'BALANCE_WITHDRAW',
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
echo "Electronic account balance withdraw protocol: $passed/"
    . count($tests) . " passed\n";
