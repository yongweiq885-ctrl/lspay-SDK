<?php
/**
 * 快捷支付申请协议回归测试。
 *
 * 运行：php php/tests/QuickPayApplyProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\QuickPayApplyRequest;
use LsPaySdk\request\UnifiedOrderChannelExtra;
use LsPaySdk\request\UnifiedOrderRequest;
use LsPaySdk\response\QuickPayApplyResponse;

final class QuickPayApplyTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"成功","data":{"err_msg":"申请处理中","mch_order_no":"QPAY1","pay_order_id":"8406518851480584192","amount":100,"order_state":1,"trans_status":"P"}}';

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

function quickPayExtra()
{
    return (new UnifiedOrderChannelExtra())
        ->setChannelUser('6666000180265827')
        ->setChannelCardId('10061758659');
}

function quickPayRequest()
{
    return (new QuickPayApplyRequest())
        ->setMchOrderNo('QPAY1')
        ->setAmount(100)
        ->setCurrency('cny')
        ->setSubject('快捷支付测试')
        ->setBody('储蓄卡快捷支付测试')
        ->setClientIp('127.0.0.1')
        ->setExpiredTime(3600)
        ->setChannelExtra(quickPayExtra());
}

function quickPayAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function quickPayAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function quickPayAssertException($class, callable $callback, $message)
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

$tests['fixed way code and typed channel extra'] = function () {
    $request = quickPayRequest();
    $request->validate();
    quickPayAssertSame(WayCodeEnum::BANK_FAST, $request->getWayCode(), '固定支付方式');
    quickPayAssertSame(
        '{"channel_user":"6666000180265827","channel_card_id":"10061758659"}',
        $request->getChannelExtra(),
        '绑卡标识应稳定序列化'
    );

    quickPayAssertException(
        LsPayValidationException::class,
        function () { quickPayRequest()->setWayCode(WayCodeEnum::BANK_FAST_JUMP); },
        '快捷支付申请不允许修改 way_code'
    );
};

$tests['binding identifiers required'] = function () {
    foreach ([
        (new QuickPayApplyRequest())
            ->setMchOrderNo('QPAY2')
            ->setAmount(100)
            ->setSubject('测试')
            ->setBody('测试'),
        quickPayRequest()->setChannelExtra(['channel_user' => 'USER_ONLY']),
        quickPayRequest()->setChannelExtra(['channel_card_id' => 'CARD_ONLY']),
    ] as $invalid) {
        quickPayAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            'channel_user 与 channel_card_id 均为必填'
        );
    }

    $generic = (new UnifiedOrderRequest())
        ->setMchOrderNo('QPAY_GENERIC')
        ->setWayCode(WayCodeEnum::BANK_FAST)
        ->setAmount(100)
        ->setSubject('测试')
        ->setBody('测试');
    quickPayAssertException(
        LsPayValidationException::class,
        function () use ($generic) { $generic->validate(); },
        '通用统一下单也不能绕过 BANK_FAST 渠道字段校验'
    );
};

$tests['endpoint payload and processing response'] = function () {
    $client = new QuickPayApplyTestClient();
    $response = $client->postRequest(quickPayRequest());

    quickPayAssertTrue(
        $response instanceof QuickPayApplyResponse,
        '应返回快捷支付申请响应对象'
    );
    quickPayAssertSame(
        'https://api.lspay.tech/gateway/unifiedorder',
        $client->capturedUrl,
        '应复用统一下单端点'
    );
    quickPayAssertSame(WayCodeEnum::BANK_FAST, $client->capturedData['way_code'], '支付方式');
    quickPayAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    quickPayAssertTrue($response->isAccepted(), 'P 状态申请已受理');
    quickPayAssertTrue($response->isProcessing(), 'P 状态等待短信确认');
    quickPayAssertTrue(!$response->isPaid(), 'order_state=1 尚未支付');
};

$tests['successful payment semantics'] = function () {
    $client = new QuickPayApplyTestClient();
    $client->responseBody = '{"status":200,"msg":"成功","data":{"pay_order_id":"P1","mch_order_no":"QPAY1","amount":100,"order_state":2,"trans_status":"S"}}';
    $response = $client->postRequest(quickPayRequest());

    quickPayAssertTrue($response->isAccepted(), 'S 状态申请已受理');
    quickPayAssertTrue(!$response->isProcessing(), 'S 状态不是处理中');
    quickPayAssertTrue($response->isPaid(), 'order_state=2 表示已支付');
};

$tests['compatible array call routes typed request'] = function () {
    $client = new QuickPayApplyTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::UNIFIED_ORDER,
        'params' => [
            'mch_order_no' => 'QPAY_ARRAY',
            'way_code' => WayCodeEnum::BANK_FAST,
            'amount' => 100,
            'currency' => 'cny',
            'subject' => '快捷支付',
            'body' => '数组调用',
            'channel_extra' => [
                'channel_user' => 'USER1',
                'channel_card_id' => 'CARD1',
            ],
        ],
    ]);
    quickPayAssertTrue(
        $response instanceof QuickPayApplyResponse,
        'BANK_FAST 数组调用应路由到快捷支付响应'
    );
};

$tests['binding identifiers are redacted'] = function () {
    $client = new QuickPayApplyTestClient();
    $safe = $client->sanitized([
        'channel_extra' => quickPayExtra()->toJson(),
    ]);
    $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE);
    quickPayAssertTrue(
        strpos($encoded, '6666000180265827') === false
            && strpos($encoded, '10061758659') === false,
        '日志不得包含快捷支付绑卡标识'
    );
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Quick pay apply protocol: $passed/" . count($tests) . " passed\n";
