<?php
/**
 * 快捷支付绑卡确认协议回归测试。
 *
 * 运行：php php/tests/QuickPayBindConfirmProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\QuickPayBindConfirmChannelExtra;
use LsPaySdk\request\QuickPayBindConfirmRequest;
use LsPaySdk\response\QuickPayBindConfirmResponse;

final class QuickPayBindConfirmTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"处理成功","data":{"trans_status":"S","channel_user":"6666000135289850","channel_card_id":"10042880209","resp_desc":"成功"}}';

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

function bindConfirmExtra()
{
    return (new QuickPayBindConfirmChannelExtra())
        ->setMchUserId('USER1')
        ->setCertType('00')
        ->setCardId('6214674220016411530')
        ->setCardName('陈大海')
        ->setCertId('610621198404070411')
        ->setCardMp('13324502911')
        ->setDcType('C');
}

function bindConfirmRequest()
{
    return (new QuickPayBindConfirmRequest())
        ->setOrderId('BIND_APPLY_1')
        ->setVerifyCode('123456')
        ->setClientIp('127.0.0.1')
        ->setChannelExtra(bindConfirmExtra());
}

function bindConfirmAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function bindConfirmAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function bindConfirmAssertException($class, callable $callback, $message)
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

$tests['typed channel extra'] = function () {
    $request = bindConfirmRequest();
    $request->validate();
    $extra = json_decode($request->getChannelExtra(), true);

    bindConfirmAssertSame('00', $extra['cert_type'], '默认身份证类型');
    bindConfirmAssertSame('C', $extra['dc_type'], '信用卡类型');
    bindConfirmAssertSame('13324502911', $extra['card_mp'], '银行预留手机号');
};

$tests['required fields and fixed way code'] = function () {
    foreach ([
        new QuickPayBindConfirmRequest(),
        (new QuickPayBindConfirmRequest())
            ->setOrderId('BIND1')
            ->setVerifyCode('123456'),
        bindConfirmRequest()->setClientIp('999.1.1.1'),
        bindConfirmRequest()->setOrderId(str_repeat('A', 31)),
    ] as $invalid) {
        bindConfirmAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '必填字段或请求格式非法时应拒绝'
        );
    }
    bindConfirmAssertException(
        LsPayValidationException::class,
        function () {
            bindConfirmRequest()->setWayCode(WayCodeEnum::BANK_FAST_JUMP);
        },
        'way_code 只能是 BANK_FAST'
    );
};

$tests['verification code compatibility'] = function () {
    foreach (['123', '123456', '12345678'] as $code) {
        bindConfirmRequest()->setVerifyCode($code)->validate();
    }
    foreach (['12', '123456789', '12A456'] as $code) {
        $invalid = bindConfirmRequest()->setVerifyCode($code);
        bindConfirmAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '短信验证码只允许 3-8 位数字'
        );
    }
};

$tests['endpoint payload and wrapped response'] = function () {
    $client = new QuickPayBindConfirmTestClient();
    $response = $client->postRequest(bindConfirmRequest());

    bindConfirmAssertTrue(
        $response instanceof QuickPayBindConfirmResponse,
        '应返回绑卡确认响应对象'
    );
    bindConfirmAssertSame(
        'https://api.lspay.tech/gateway/quickpay/buckleconfirm',
        $client->capturedUrl,
        '接口 URL'
    );
    bindConfirmAssertSame(WayCodeEnum::BANK_FAST, $client->capturedData['way_code'], '固定支付方式');
    bindConfirmAssertSame('123456', $client->capturedData['verify_code'], '短信验证码');
    bindConfirmAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    bindConfirmAssertTrue($response->hasBinding(), '成功响应应包含完整绑卡标识');
    bindConfirmAssertSame('6666000135289850', $response->getChannelUser(), '渠道用户 ID');
    bindConfirmAssertSame('10042880209', $response->getChannelCardId(), '绑卡 ID');
};

$tests['flat response compatibility'] = function () {
    $processing = new QuickPayBindConfirmResponse([
        'trans_status' => 'P',
        'channel_user' => 'USER1',
        'channel_card_id' => '',
        'resp_desc' => '处理中',
    ]);
    bindConfirmAssertTrue($processing->isProcessing(), '平铺 P 状态应为处理中');
    bindConfirmAssertTrue(!$processing->hasBinding(), '处理中不得保存绑卡结果');

    $failed = new QuickPayBindConfirmResponse([
        'trans_status' => 'F',
        'resp_desc' => '验证码错误',
    ]);
    bindConfirmAssertTrue($failed->isError(), '平铺 F 状态应为失败');
};

$tests['compatible array call uses validation'] = function () {
    $client = new QuickPayBindConfirmTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::QUICK_PAY_BIND_CONFIRM,
        'params' => [
            'order_id' => 'BIND_ARRAY',
            'way_code' => WayCodeEnum::BANK_FAST,
            'verify_code' => '123456',
            'channel_extra' => bindConfirmExtra()->toArray(),
        ],
    ]);
    bindConfirmAssertTrue(
        $response instanceof QuickPayBindConfirmResponse,
        '兼容数组调用也应返回类型化响应'
    );

    bindConfirmAssertException(
        LsPayValidationException::class,
        function () use ($client) {
            $client->postRequest([
                'funcCode' => EndpointEnum::QUICK_PAY_BIND_CONFIRM,
                'params' => [
                    'order_id' => 'BIND_INVALID',
                    'verify_code' => '123456',
                ],
            ]);
        },
        '兼容数组调用不能绕过 channel_extra 校验'
    );
};

$tests['sensitive confirmation fields are redacted'] = function () {
    $client = new QuickPayBindConfirmTestClient();
    $safe = $client->sanitized([
        'verify_code' => '123456',
        'channel_extra' => bindConfirmExtra()->toJson(),
        'data' => [
            'channel_user' => '6666000135289850',
            'channel_card_id' => '10042880209',
        ],
    ]);
    $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE);
    foreach ([
        '123456',
        '6214674220016411530',
        '610621198404070411',
        '6666000135289850',
        '10042880209',
    ] as $secret) {
        bindConfirmAssertTrue(
            strpos($encoded, $secret) === false,
            "日志不得包含绑卡确认敏感值 $secret"
        );
    }
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Quick pay bind confirm protocol: $passed/" . count($tests) . " passed\n";
