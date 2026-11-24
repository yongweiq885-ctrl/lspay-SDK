<?php
/**
 * 快捷支付确认协议回归测试。
 *
 * 运行：php php/tests/QuickPayConfirmProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\QuickPayConfirmRequest;
use LsPaySdk\response\QuickPayConfirmResponse;

final class QuickPayConfirmTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"成功","data":{"trans_status":"S","err_msg":"支付确认成功"}}';

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

function quickConfirmRequest()
{
    return (new QuickPayConfirmRequest())
        ->setPayOrderId('8708459403556159488')
        ->setVerifyCode('123456')
        ->setClientIp('127.0.0.1');
}

function quickConfirmAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function quickConfirmAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function quickConfirmAssertException($class, callable $callback, $message)
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

$tests['identifier validation'] = function () {
    (new QuickPayConfirmRequest())
        ->setMchOrderNo('MCH_ORDER_1')
        ->setVerifyCode('123456')
        ->validate();

    foreach ([
        (new QuickPayConfirmRequest())->setVerifyCode('123456'),
        quickConfirmRequest()->setPayOrderId(str_repeat('P', 31)),
    ] as $invalid) {
        quickConfirmAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '订单标识缺失或超长时应拒绝'
        );
    }
};

$tests['fixed fields and verification code'] = function () {
    quickConfirmAssertSame(
        WayCodeEnum::BANK_FAST,
        quickConfirmRequest()->getWayCode(),
        '支付方式固定为 BANK_FAST'
    );
    foreach (['12345', '1234567', '12A456'] as $code) {
        $invalid = quickConfirmRequest()->setVerifyCode($code);
        quickConfirmAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            'verify_code 必须为 6 位数字'
        );
    }
    quickConfirmAssertException(
        LsPayValidationException::class,
        function () { quickConfirmRequest()->setWayCode(WayCodeEnum::BANK_FAST_JUMP); },
        'way_code 不允许修改'
    );
};

$tests['endpoint payload and successful response'] = function () {
    $client = new QuickPayConfirmTestClient();
    $response = $client->postRequest(quickConfirmRequest());

    quickConfirmAssertTrue(
        $response instanceof QuickPayConfirmResponse,
        '应返回快捷支付确认响应对象'
    );
    quickConfirmAssertSame(
        'https://api.lspay.tech/gateway/quickpay/payconfirm',
        $client->capturedUrl,
        '接口 URL'
    );
    quickConfirmAssertSame(WayCodeEnum::BANK_FAST, $client->capturedData['way_code'], '固定支付方式');
    quickConfirmAssertSame('123456', $client->capturedData['verify_code'], '短信验证码');
    quickConfirmAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    quickConfirmAssertTrue($response->isSuccessful(), 'trans_status=S 应为成功');
};

$tests['processing and flat failure semantics'] = function () {
    $client = new QuickPayConfirmTestClient();
    $client->responseBody = '{"status":200,"msg":"成功","data":{"trans_status":"P","err_msg":"验证中"}}';
    $processing = $client->postRequest(quickConfirmRequest());
    quickConfirmAssertTrue($processing->isProcessing(), 'P 状态应为处理中');
    quickConfirmAssertTrue(!$processing->isSuccessful(), 'P 状态不是最终成功');

    $failed = new QuickPayConfirmResponse([
        'trans_status' => 'F',
        'err_msg' => '验证码错误',
    ]);
    quickConfirmAssertTrue($failed->isError(), '平铺 F 状态应为失败');
    quickConfirmAssertSame('验证码错误', $failed->getErrMsg(), '渠道错误描述');
};

$tests['compatible array call uses validation'] = function () {
    $client = new QuickPayConfirmTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::QUICK_PAY_CONFIRM,
        'params' => [
            'way_code' => WayCodeEnum::BANK_FAST,
            'mch_order_no' => 'MCH_ORDER_ARRAY',
            'verify_code' => '123456',
        ],
    ]);
    quickConfirmAssertTrue(
        $response instanceof QuickPayConfirmResponse,
        '兼容数组调用也应返回类型化响应'
    );

    quickConfirmAssertException(
        LsPayValidationException::class,
        function () use ($client) {
            $client->postRequest([
                'funcCode' => EndpointEnum::QUICK_PAY_CONFIRM,
                'params' => ['verify_code' => '123456'],
            ]);
        },
        '兼容数组调用不能绕过订单标识校验'
    );
};

$tests['verification code is redacted'] = function () {
    $client = new QuickPayConfirmTestClient();
    $safe = $client->sanitized(['verify_code' => '123456']);
    quickConfirmAssertTrue(
        strpos(json_encode($safe), '123456') === false,
        '日志不得包含短信验证码'
    );
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Quick pay confirm protocol: $passed/" . count($tests) . " passed\n";
