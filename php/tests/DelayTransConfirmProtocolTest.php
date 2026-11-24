<?php
/**
 * 延时分账交易确认协议回归测试。
 *
 * 运行：php php/tests/DelayTransConfirmProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\DelayTransConfirmRequest;
use LsPaySdk\request\DivisionChannelExtra;
use LsPaySdk\request\DivisionReceiver;
use LsPaySdk\response\DelayTransConfirmResponse;

final class DelayTransConfirmTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"交易成功","data":{"unconfirm_amount":0,"confirmed_amount":100,"confirm_order_id":"AD9A2DA9F","batch_order_id":"BATCH1","trans_status":"S","err_msg":"确认成功"}}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }
}

function confirmRequest()
{
    return (new DelayTransConfirmRequest())
        ->setPayOrderId('202106181104177050002')
        ->setOrderId('CONFIRM' . time());
}

function confirmAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function confirmAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function confirmAssertValidation(callable $callback, $message)
{
    try {
        $callback();
    } catch (LsPayValidationException $e) {
        return;
    }
    throw new RuntimeException($message);
}

LsPay::init([
    'mch_no' => 'TEST_MERCHANT',
    'app_id' => 'TEST_APP',
    'md5_key' => 'test-secret',
], true);

$tests = [];

$tests['identifiers and order id'] = function () {
    foreach ([
        (new DelayTransConfirmRequest())->setOrderId('CONFIRM1'),
        (new DelayTransConfirmRequest())->setPayOrderId('P1'),
        confirmRequest()->setOrderId(str_repeat('A', 31)),
        (new DelayTransConfirmRequest())
            ->setPayOrderId(str_repeat('P', 31))
            ->setOrderId('CONFIRM2'),
    ] as $invalid) {
        confirmAssertValidation(function () use ($invalid) {
            $invalid->validate();
        }, '订单标识或确认流水号不符合协议时应拒绝');
    }

    (new DelayTransConfirmRequest())
        ->setMchOrderNo('MCH_ORDER_1')
        ->setOrderId('CONFIRM3')
        ->validate();
};

$tests['typed division channel extra'] = function () {
    $extra = (new DivisionChannelExtra())
        ->addDivisionReceiver(
            (new DivisionReceiver('123'))
                ->setDivAmt(10)
                ->setDescription('经销商')
        )
        ->addDivisionReceiver(
            (new DivisionReceiver('456'))
                ->setDivAmt(10)
                ->setDescription('渠道商')
        );
    $request = confirmRequest()->setChannelExtra($extra);
    $request->validate();
    confirmAssertSame(
        '{"div_list":[{"trans_in":"123","div_amt":10,"description":"经销商"},{"trans_in":"456","div_amt":10,"description":"渠道商"}]}',
        $request->getChannelExtra(),
        '类型化分账参数应稳定序列化'
    );
};

$tests['division list validation'] = function () {
    foreach ([
        ['div_list' => []],
        ['div_list' => [['div_amt' => 10]]],
        ['div_list' => [['trans_in' => 123, 'div_amt' => 10]]],
        ['div_list' => [['trans_in' => '123', 'div_amt' => 0]]],
        ['div_list' => [['trans_in' => '123', 'div_amt' => '10']]],
    ] as $extra) {
        $invalid = confirmRequest()->setChannelExtra($extra);
        confirmAssertValidation(function () use ($invalid) {
            $invalid->validate();
        }, '非法 div_list 应被拒绝');
    }
};

$tests['endpoint payload and typed response'] = function () {
    $client = new DelayTransConfirmTestClient();
    $response = $client->postRequest(
        confirmRequest()->setChannelExtra([
            'div_list' => [[
                'trans_in' => '123',
                'div_amt' => 10,
                'description' => '经销商',
            ]],
        ])
    );

    confirmAssertTrue(
        $response instanceof DelayTransConfirmResponse,
        '应返回延时分账交易确认响应对象'
    );
    confirmAssertSame(
        'https://api.lspay.tech/gateway/delaytrans/confirm',
        $client->capturedUrl,
        '接口 URL'
    );
    confirmAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    confirmAssertSame(100, $response->getConfirmedAmount(), '已确认金额');
    confirmAssertSame(0, $response->getUnconfirmAmount(), '待确认金额');
    confirmAssertSame('AD9A2DA9F', $response->getConfirmOrderId(), '确认流水号');
    confirmAssertSame('BATCH1', $response->getBatchOrderId(), '系统流水号');
    confirmAssertTrue($response->isAccepted(), 'status=200 应为接口已受理');
    confirmAssertTrue($response->isSuccessful(), 'trans_status=S 应为确认成功');
};

$tests['failed transaction and compatible array call'] = function () {
    $client = new DelayTransConfirmTestClient();
    $client->responseBody = '{"status":200,"msg":"交易成功","data":{"trans_status":"F","err_code":"900000","err_msg":"确认失败"}}';
    $failed = $client->postRequest(confirmRequest());
    confirmAssertTrue($failed->isAccepted(), '接口调用仍成功');
    confirmAssertTrue(!$failed->isSuccessful(), 'trans_status=F 不得判断为确认成功');
    confirmAssertSame('900000', $failed->getErrCode(), '渠道错误码');

    $response = $client->postRequest([
        'funcCode' => EndpointEnum::DELAY_TRANS_CONFIRM,
        'params' => [
            'mch_order_no' => 'MCH_ORDER_ARRAY',
            'order_id' => 'CONFIRM_ARRAY',
        ],
    ]);
    confirmAssertTrue(
        $response instanceof DelayTransConfirmResponse,
        '兼容数组调用也应返回类型化响应'
    );

    confirmAssertValidation(function () use ($client) {
        $client->postRequest([
            'funcCode' => EndpointEnum::DELAY_TRANS_CONFIRM,
            'params' => ['order_id' => 'CONFIRM_INVALID'],
        ]);
    }, '兼容数组调用不能绕过订单标识校验');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Delay trans confirm protocol: $passed/" . count($tests) . " passed\n";
