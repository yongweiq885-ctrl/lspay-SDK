<?php
/**
 * 快捷支付解绑协议回归测试。
 *
 * 运行：php php/tests/QuickPayUnbindProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\QuickPayUnbindChannelExtra;
use LsPaySdk\request\QuickPayUnbindRequest;
use LsPaySdk\response\QuickPayUnbindResponse;

final class QuickPayUnbindTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"成功","data":{"trans_status":"S","order_id":"UNBIND1","resp_desc":"成功"}}';

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

function unbindExtra()
{
    return (new QuickPayUnbindChannelExtra())
        ->setMchUserId('USER1')
        ->setChannelCardId('7422001641112');
}

function unbindRequest()
{
    return (new QuickPayUnbindRequest())
        ->setOrderId('UNBIND1')
        ->setClientIp('127.0.0.1')
        ->setChannelExtra(unbindExtra());
}

function unbindAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function unbindAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function unbindAssertException($class, callable $callback, $message)
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
    $request = unbindRequest();
    $request->validate();
    unbindAssertSame(
        '{"mch_user_id":"USER1","channel_card_id":"7422001641112"}',
        $request->getChannelExtra(),
        '解绑渠道参数应稳定序列化'
    );
};

$tests['required fields and fixed way code'] = function () {
    foreach ([
        new QuickPayUnbindRequest(),
        (new QuickPayUnbindRequest())->setOrderId('UNBIND1'),
        unbindRequest()->setChannelExtra(['mch_user_id' => 'USER_ONLY']),
        unbindRequest()->setChannelExtra(['channel_card_id' => 'CARD_ONLY']),
        unbindRequest()->setClientIp('999.1.1.1'),
    ] as $invalid) {
        unbindAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '必填字段或请求格式非法时应拒绝'
        );
    }
    unbindAssertException(
        LsPayValidationException::class,
        function () { unbindRequest()->setWayCode(WayCodeEnum::BANK_FAST_JUMP); },
        'way_code 只能是 BANK_FAST'
    );
};

$tests['endpoint payload and successful response'] = function () {
    $client = new QuickPayUnbindTestClient();
    $response = $client->postRequest(unbindRequest());

    unbindAssertTrue(
        $response instanceof QuickPayUnbindResponse,
        '应返回快捷支付解绑响应对象'
    );
    unbindAssertSame(
        'https://api.lspay.tech/gateway/quickpay/payunbind',
        $client->capturedUrl,
        '接口 URL'
    );
    unbindAssertSame(WayCodeEnum::BANK_FAST, $client->capturedData['way_code'], '固定支付方式');
    unbindAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    unbindAssertTrue($response->isSuccessful(), 'trans_status=S 应为解绑成功');
    unbindAssertSame('UNBIND1', $response->getOrderId(), '商户解绑流水号');
};

$tests['flat response compatibility'] = function () {
    $processing = new QuickPayUnbindResponse([
        'trans_status' => 'P',
        'order_id' => 'UNBIND2',
        'resp_desc' => '处理中',
    ]);
    unbindAssertTrue($processing->isProcessing(), '平铺 P 状态应为处理中');

    $failed = new QuickPayUnbindResponse([
        'trans_status' => 'F',
        'order_id' => 'UNBIND3',
        'resp_desc' => '解绑失败',
    ]);
    unbindAssertTrue($failed->isError(), '平铺 F 状态应为失败');
};

$tests['compatible array call uses validation'] = function () {
    $client = new QuickPayUnbindTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::QUICK_PAY_UNBIND,
        'params' => [
            'order_id' => 'UNBIND_ARRAY',
            'way_code' => WayCodeEnum::BANK_FAST,
            'channel_extra' => unbindExtra()->toArray(),
        ],
    ]);
    unbindAssertTrue(
        $response instanceof QuickPayUnbindResponse,
        '兼容数组调用也应返回类型化响应'
    );

    unbindAssertException(
        LsPayValidationException::class,
        function () use ($client) {
            $client->postRequest([
                'funcCode' => EndpointEnum::QUICK_PAY_UNBIND,
                'params' => ['order_id' => 'UNBIND_INVALID'],
            ]);
        },
        '兼容数组调用不能绕过 channel_extra 校验'
    );
};

$tests['unbind identifiers are redacted'] = function () {
    $client = new QuickPayUnbindTestClient();
    $safe = $client->sanitized([
        'channel_extra' => unbindExtra()->toJson(),
    ]);
    $encoded = json_encode($safe);
    unbindAssertTrue(
        strpos($encoded, 'USER1') === false
            && strpos($encoded, '7422001641112') === false,
        '日志不得包含解绑用户与绑卡标识'
    );
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Quick pay unbind protocol: $passed/" . count($tests) . " passed\n";
