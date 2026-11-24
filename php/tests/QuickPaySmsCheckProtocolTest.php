<?php
/**
 * 快捷支付短信校验协议回归测试。
 *
 * 运行：php php/tests/QuickPaySmsCheckProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\QuickPaySmsCheckRequest;
use LsPaySdk\response\QuickPaySmsCheckResponse;

final class QuickPaySmsCheckTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"成功","data":{"trans_status":"S","err_msg":"短信校验成功"}}';

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

function smsCheckRequest()
{
    return (new QuickPaySmsCheckRequest())
        ->setPayOrderId('8708459403556159488')
        ->setVerifyCode('123456')
        ->setClientIp('127.0.0.1');
}

function smsCheckAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function smsCheckAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function smsCheckAssertException($class, callable $callback, $message)
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

$tests['inherits payment confirmation validation'] = function () {
    smsCheckRequest()->validate();
    (new QuickPaySmsCheckRequest())
        ->setMchOrderNo('MCH_ORDER_1')
        ->setVerifyCode('123456')
        ->validate();

    foreach ([
        (new QuickPaySmsCheckRequest())->setVerifyCode('123456'),
        smsCheckRequest()->setVerifyCode('12345'),
        smsCheckRequest()->setClientIp('999.1.1.1'),
    ] as $invalid) {
        smsCheckAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '短信校验必须复用支付确认字段规则'
        );
    }
};

$tests['endpoint payload and successful response'] = function () {
    $client = new QuickPaySmsCheckTestClient();
    $response = $client->postRequest(smsCheckRequest());

    smsCheckAssertTrue(
        $response instanceof QuickPaySmsCheckResponse,
        '应返回快捷支付短信校验响应对象'
    );
    smsCheckAssertSame(
        'https://api.lspay.tech/gateway/quickpay/smscheck',
        $client->capturedUrl,
        '接口 URL'
    );
    smsCheckAssertSame(WayCodeEnum::BANK_FAST, $client->capturedData['way_code'], '固定支付方式');
    smsCheckAssertSame('123456', $client->capturedData['verify_code'], '短信验证码');
    smsCheckAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    smsCheckAssertTrue($response->isSuccessful(), 'trans_status=S 应为校验成功');
};

$tests['processing and failure semantics'] = function () {
    $client = new QuickPaySmsCheckTestClient();
    $client->responseBody = '{"status":200,"msg":"成功","data":{"trans_status":"P","err_msg":"验证中"}}';
    $processing = $client->postRequest(smsCheckRequest());
    smsCheckAssertTrue($processing->isProcessing(), 'P 状态应为校验中');

    $failed = new QuickPaySmsCheckResponse([
        'trans_status' => 'F',
        'err_msg' => '验证码错误',
    ]);
    smsCheckAssertTrue($failed->isError(), '平铺 F 状态应为校验失败');
};

$tests['compatible array call uses validation'] = function () {
    $client = new QuickPaySmsCheckTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::QUICK_PAY_SMS_CHECK,
        'params' => [
            'way_code' => WayCodeEnum::BANK_FAST,
            'pay_order_id' => 'PAY_ARRAY',
            'verify_code' => '123456',
        ],
    ]);
    smsCheckAssertTrue(
        $response instanceof QuickPaySmsCheckResponse,
        '兼容数组调用也应返回类型化响应'
    );

    smsCheckAssertException(
        LsPayValidationException::class,
        function () use ($client) {
            $client->postRequest([
                'funcCode' => EndpointEnum::QUICK_PAY_SMS_CHECK,
                'params' => ['verify_code' => '123456'],
            ]);
        },
        '兼容数组调用不能绕过订单标识校验'
    );
};

$tests['verification code is redacted'] = function () {
    $client = new QuickPaySmsCheckTestClient();
    $safe = $client->sanitized(['verify_code' => '123456']);
    smsCheckAssertTrue(
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
echo "Quick pay SMS check protocol: $passed/" . count($tests) . " passed\n";
