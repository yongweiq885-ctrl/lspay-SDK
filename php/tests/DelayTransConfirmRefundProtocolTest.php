<?php
/**
 * 交易确认退款协议回归测试。
 *
 * 运行：php php/tests/DelayTransConfirmRefundProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\DelayTransConfirmRefundRequest;
use LsPaySdk\response\DelayTransConfirmRefundResponse;

final class DelayTransConfirmRefundTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"交易成功","data":{"unconfirm_amount":100,"confirmed_amount":0,"confirm_order_id":"WX1KiEs","confirm_refund_no":"AD9A2DA9F","trans_status":"S","err_msg":"退款成功"}}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }
}

function confirmRefundRequest()
{
    return (new DelayTransConfirmRefundRequest())
        ->setConfirmOrderId('27210632100491')
        ->setConfirmRefundNo('CREFUND' . time());
}

function confirmRefundAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function confirmRefundAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function confirmRefundAssertValidation(callable $callback, $message)
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

$tests['required fields and length'] = function () {
    foreach ([
        (new DelayTransConfirmRefundRequest())
            ->setConfirmRefundNo('CREFUND1'),
        (new DelayTransConfirmRefundRequest())
            ->setConfirmOrderId('CONFIRM1'),
        confirmRefundRequest()->setConfirmOrderId(str_repeat('A', 31)),
        confirmRefundRequest()->setConfirmRefundNo(str_repeat('B', 31)),
    ] as $invalid) {
        confirmRefundAssertValidation(function () use ($invalid) {
            $invalid->validate();
        }, '必填字段缺失或超过 30 字符时应拒绝');
    }
};

$tests['channel extra normalization'] = function () {
    $request = confirmRefundRequest()->setChannelExtra([
        'channel_refund_mode' => 'original',
    ]);
    $request->validate();
    confirmRefundAssertSame(
        '{"channel_refund_mode":"original"}',
        $request->getChannelExtra(),
        'channel_extra 应规范化为稳定 JSON'
    );

    foreach ([
        '[1,2]',
        '{invalid-json}',
        str_repeat('A', 257),
    ] as $invalidExtra) {
        confirmRefundAssertValidation(function () use ($invalidExtra) {
            confirmRefundRequest()->setChannelExtra($invalidExtra)->validate();
        }, '非法 channel_extra 应被拒绝');
    }
};

$tests['endpoint payload and typed response'] = function () {
    $client = new DelayTransConfirmRefundTestClient();
    $response = $client->postRequest(confirmRefundRequest());

    confirmRefundAssertTrue(
        $response instanceof DelayTransConfirmRefundResponse,
        '应返回交易确认退款响应对象'
    );
    confirmRefundAssertSame(
        'https://api.lspay.tech/gateway/delaytrans/confirmrefund',
        $client->capturedUrl,
        '接口 URL'
    );
    confirmRefundAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    confirmRefundAssertSame(100, $response->getUnconfirmAmount(), '待确认金额');
    confirmRefundAssertSame(0, $response->getConfirmedAmount(), '已确认金额');
    confirmRefundAssertSame('WX1KiEs', $response->getConfirmOrderId(), '确认流水号');
    confirmRefundAssertSame(
        'AD9A2DA9F',
        $response->getConfirmRefundNo(),
        '确认退款流水号'
    );
    confirmRefundAssertTrue($response->isAccepted(), 'status=200 应为接口已受理');
    confirmRefundAssertTrue($response->isSuccessful(), 'trans_status=S 应为退款成功');
};

$tests['failed transaction semantics'] = function () {
    $client = new DelayTransConfirmRefundTestClient();
    $client->responseBody = '{"status":200,"msg":"交易成功","data":{"trans_status":"F","err_code":"ACQ.FAIL","err_msg":"Business Failed"}}';
    $response = $client->postRequest(confirmRefundRequest());

    confirmRefundAssertTrue($response->isAccepted(), '接口调用仍成功');
    confirmRefundAssertTrue(!$response->isSuccessful(), 'trans_status=F 不得判断为退款成功');
    confirmRefundAssertSame('ACQ.FAIL', $response->getErrCode(), '渠道错误码');
    confirmRefundAssertSame('Business Failed', $response->getErrMsg(), '渠道错误描述');
};

$tests['compatible array call uses validation'] = function () {
    $client = new DelayTransConfirmRefundTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::DELAY_TRANS_CONFIRM_REFUND,
        'params' => [
            'confirm_order_id' => 'CONFIRM_ARRAY',
            'confirm_refund_no' => 'CREFUND_ARRAY',
        ],
    ]);
    confirmRefundAssertTrue(
        $response instanceof DelayTransConfirmRefundResponse,
        '兼容数组调用也应返回类型化响应'
    );

    confirmRefundAssertValidation(function () use ($client) {
        $client->postRequest([
            'funcCode' => EndpointEnum::DELAY_TRANS_CONFIRM_REFUND,
            'params' => ['confirm_order_id' => 'CONFIRM_ONLY'],
        ]);
    }, '兼容数组调用不能绕过必填字段校验');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Delay trans confirm refund protocol: $passed/" . count($tests) . " passed\n";
