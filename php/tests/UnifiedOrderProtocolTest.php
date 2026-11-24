<?php
/**
 * PHP 7.4+ 无第三方依赖协议回归测试。
 *
 * 运行：php php/tests/UnifiedOrderProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\PayDataTypeEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\DivisionReceiver;
use LsPaySdk\request\UnifiedOrderChannelExtra;
use LsPaySdk\request\UnifiedOrderRequest;
use LsPaySdk\response\UnifiedOrderResponse;

final class ProtocolTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"成功","data":{"mch_order_no":"T1","pay_order_id":"P1","amount":100,"order_state":0,"pay_info":"https://pay.test","trans_status":"S"}}';

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

function validRequest()
{
    return (new UnifiedOrderRequest())
        ->setMchOrderNo('TEST' . time())
        ->setWayCode(WayCodeEnum::ALI_QR)
        ->setAmount(100)
        ->setCurrency('cny')
        ->setSubject('测试商品')
        ->setBody('统一下单协议测试')
        ->setChannelExtra('{}');
}

function assertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function assertValidation(callable $callback, $message)
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
    'notify_url' => 'https://merchant.test/notify',
    'return_url' => 'https://merchant.test/return',
], true);

$tests = [];

$tests['valid request and normalization'] = function () {
    $extra = (new UnifiedOrderChannelExtra())
        ->setPayDataType(PayDataTypeEnum::CODE_IMG_URL);
    $request = validRequest()
        ->setExpiredTime(7200)
        ->setDivisionMode(0)
        ->setPollingMode(3)
        ->setChannelExtra($extra);
    $request->validate();
    assertSameValue(
        '{"pay_data_type":"code_img_url"}',
        $request->getChannelExtra(),
        'channel_extra 应规范化为稳定 JSON'
    );
};

$tests['required body'] = function () {
    $request = validRequest()->setBody('');
    assertValidation(function () use ($request) {
        $request->validate();
    }, '缺少 body 应被拒绝');
};

$tests['required channel_extra'] = function () {
    foreach ([null, ''] as $value) {
        $request = validRequest()->setChannelExtra($value);
        try {
            $request->validate();
        } catch (LsPayValidationException $e) {
            assertSameValue('channel_extra 必填', $e->getMessage(), '缺少 channel_extra 的错误信息');
            continue;
        }
        throw new RuntimeException('缺少 channel_extra 应被拒绝');
    }
};

$tests['cashier uses browser form'] = function () {
    $client = new ProtocolTestClient();
    $request = validRequest()->setChannelExtra(['pay_data_type' => PayDataTypeEnum::CASHIER]);
    try {
        $client->postRequest($request);
        throw new RuntimeException('cashier 不应由服务端调用接口');
    } catch (LsPayValidationException $e) {
        assertTrue(strpos($e->getMessage(), 'buildCashierForm') !== false, '应提示使用 buildCashierForm');
    }
    assertSameValue('', $client->capturedUrl, 'cashier 不应发出 HTTP 请求');

    $form = $client->buildCashierForm($request);
    assertSameValue('POST', $form['method'], '收银台表单应使用 POST');
    assertTrue(substr($form['action'], -strlen(EndpointEnum::UNIFIED_ORDER)) === EndpointEnum::UNIFIED_ORDER, '表单应提交到统一下单地址');
    $fields = $form['fields'];
    $sign = $fields['sign'];
    unset($fields['sign']);
    assertSameValue(\LsPaySdk\core\LsPayTools::sign($fields, 'test-secret'), $sign, '表单字段应正确签名');
    assertSameValue('{"pay_data_type":"cashier"}', $fields['channel_extra'], '应携带 cashier');
    assertSameValue('', $client->capturedUrl, '生成表单不应发出 HTTP 请求');

    $html = $client->renderCashierForm($request);
    assertTrue(strpos($html, 'name="sign"') !== false && strpos($html, '.submit()') !== false, '应生成自动提交表单');

    assertValidation(function () use ($client) {
        $client->buildCashierForm(validRequest()->setChannelExtra(['pay_data_type' => PayDataTypeEnum::PAY_URL]));
    }, '非 cashier 不应生成收银台表单');
};

$tests['way code enum'] = function () {
    $request = validRequest()->setWayCode('UNKNOWN_PAY');
    assertValidation(function () use ($request) {
        $request->validate();
    }, '非法 way_code 应被拒绝');
};

$tests['documented payment method list'] = function () {
    $methods = WayCodeEnum::paymentMethods();
    assertSameValue(20, count($methods), '支付方式文档应包含 20 项');
    foreach ([
        WayCodeEnum::APPLY,
        WayCodeEnum::BALANCE_PAY,
        WayCodeEnum::BALANCE_QUERY,
        WayCodeEnum::SETTLE_QUERY,
        WayCodeEnum::BALANCE_WITHDRAW,
        WayCodeEnum::WITHDRAW_SINGLE_QUERY,
        WayCodeEnum::WITHDRAW_QUERY,
    ] as $businessCode) {
        assertTrue(
            !array_key_exists($businessCode, $methods),
            "统一下单选择器不应包含业务代码 $businessCode"
        );
    }
};

$tests['conditional channel fields'] = function () {
    $cases = [
        [WayCodeEnum::ALI_PUB, 'buyer_id'],
        [WayCodeEnum::ALI_LITE, 'buyer_id'],
        [WayCodeEnum::WX_PUB, 'openid'],
        [WayCodeEnum::WX_LITE, 'openid'],
        [WayCodeEnum::WX_BAR, 'auth_code'],
        [WayCodeEnum::ALI_BAR, 'auth_code'],
        [WayCodeEnum::YSF_BAR, 'auth_code'],
    ];
    foreach ($cases as $case) {
        list($wayCode, $field) = $case;
        $missing = validRequest()->setWayCode($wayCode);
        assertValidation(function () use ($missing) {
            $missing->validate();
        }, "$wayCode 缺少 $field 应被拒绝");

        $valid = validRequest()
            ->setWayCode($wayCode)
            ->setChannelExtra([$field => 'channel-user-value']);
        $valid->validate();
    }

    foreach ([WayCodeEnum::WX_PUB, WayCodeEnum::ALI_PUB] as $wayCode) {
        $scan = validRequest()
            ->setWayCode($wayCode)
            ->setChannelExtra(['pay_data_type' => 'code_img_url']);
        $scan->validate();
    }
};

$tests['pay data type'] = function () {
    $request = validRequest()->setChannelExtra(['pay_data_type' => 'html']);
    assertValidation(function () use ($request) {
        $request->validate();
    }, '非法 pay_data_type 应被拒绝');
};

$tests['division polling and expiration'] = function () {
    foreach ([
        validRequest()->setDivisionMode(3),
        validRequest()->setDivisionMode(1),
        validRequest()->setDivisionMode(2)->setChannelExtra(['div_list' => []]),
        validRequest()->setPollingMode(4),
        validRequest()->setExpiredTime(0),
    ] as $request) {
        assertValidation(function () use ($request) {
            $request->validate();
        }, '非法模式或有效期应被拒绝');
    }
};

$tests['typed division receivers'] = function () {
    $extra = (new UnifiedOrderChannelExtra())
        ->addDivisionReceiver(
            (new DivisionReceiver('350873274291111'))
                ->setDivAmt(10)
                ->setDescription('经销商订单分账')
        )
        ->addDivisionReceiver(
            (new DivisionReceiver('35087342028002222'))
                ->setDescription('按配置比例分账')
        )
        ->setPayDataType(PayDataTypeEnum::CODE_IMG_URL);

    $request = validRequest()
        ->setAmount(100)
        ->setDivisionMode(1)
        ->setChannelExtra($extra);
    $request->validate();

    $decoded = json_decode($request->getChannelExtra(), true);
    assertSameValue(10, $decoded['div_list'][0]['div_amt'], '分账金额应保持整数');
    assertSameValue(
        '35087342028002222',
        $decoded['div_list'][1]['trans_in'],
        '未指定金额的接收方应正确序列化'
    );
    assertTrue(
        !isset($decoded['div_list'][1]['div_amt']),
        '未指定 div_amt 时不应发送该字段'
    );

    foreach ([
        ['div_list' => [['div_amt' => 1]]],
        ['div_list' => [['trans_in' => 123]]],
        ['div_list' => [['trans_in' => 'receiver', 'div_amt' => 0]]],
        ['div_list' => [['trans_in' => 'receiver', 'div_amt' => '10']]],
        ['div_list' => [['trans_in' => 'receiver', 'description' => ['invalid']]]],
    ] as $invalidExtra) {
        $invalid = validRequest()
            ->setDivisionMode(1)
            ->setChannelExtra($invalidExtra);
        assertValidation(function () use ($invalid) {
            $invalid->validate();
        }, '非法 div_list 项应被拒绝');
    }
};

$tests['reserved public fields'] = function () {
    $client = new ProtocolTestClient();
    $request = validRequest()->setExtendInfo(['sign' => 'FORGED']);
    assertValidation(function () use ($client, $request) {
        $client->postRequest($request);
    }, '公共签名字段不允许通过扩展参数覆盖');

    $duplicate = validRequest()->setExtendInfo(['amount' => 1]);
    assertValidation(function () use ($client, $duplicate) {
        $client->postRequest($duplicate);
    }, '扩展参数不允许覆盖业务字段');
};

$tests['request signing and typed response'] = function () {
    $client = new ProtocolTestClient();
    $request = validRequest()->setChannelExtra([
        'pay_data_type' => PayDataTypeEnum::CODE_URL,
    ]);
    $response = $client->postRequest($request);

    assertTrue($response instanceof UnifiedOrderResponse, '应返回统一下单响应对象');
    assertSameValue('https://api.lspay.tech/gateway/unifiedorder', $client->capturedUrl, '接口 URL');
    assertTrue((bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']), 'req_time 应为 13 位毫秒时间戳');
    assertSameValue('https://merchant.test/notify', $client->capturedData['notify_url'], '应使用默认 notify_url');

    $signed = $client->capturedData;
    $actualSign = $signed['sign'];
    unset($signed['sign']);
    $parts = [];
    ksort($signed);
    foreach ($signed as $key => $value) {
        if ($value !== '' && $value !== null) {
            $parts[] = $key . '=' . $value;
        }
    }
    $expectedSign = strtoupper(md5(implode('&', $parts) . '&key=test-secret'));
    assertSameValue($expectedSign, $actualSign, '签名必须使用原始值 ASCII 排序后计算');

    assertTrue($response->isAccepted(), 'status=200 且 trans_status=S 应为已受理');
    assertTrue(!$response->isPaid(), 'order_state=0 不代表支付成功');
};

$tests['response business semantics'] = function () {
    $client = new ProtocolTestClient();
    $client->responseBody = '{"status":200,"msg":"成功","data":{"order_state":2,"trans_status":"S"}}';
    $paid = $client->postRequest(validRequest());
    assertTrue($paid->isPaid(), 'order_state=2 才是支付成功');

    $client->responseBody = '{"status":400,"msg":"当前应用不存在！","data":[]}';
    $failed = $client->postRequest(validRequest());
    assertTrue($failed->isError(), 'status=400 应为业务失败');
    assertSameValue(400, $failed->getStatus(), '应保留 status');
};

$tests['compatible array call uses validation'] = function () {
    $client = new ProtocolTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::UNIFIED_ORDER,
        'params' => [
            'mch_order_no' => 'ARRAY_CALL_1',
            'way_code' => WayCodeEnum::ALI_QR,
            'amount' => 100,
            'currency' => 'cny',
            'subject' => '数组调用',
            'body' => '同样执行统一下单校验',
            'channel_extra' => ['pay_data_type' => PayDataTypeEnum::CODE_IMG_URL],
        ],
    ]);
    assertTrue($response instanceof UnifiedOrderResponse, '数组调用也应返回统一下单响应对象');

    assertValidation(function () use ($client) {
        $client->postRequest([
            'funcCode' => EndpointEnum::UNIFIED_ORDER,
            'params' => [
                'mch_order_no' => 'ARRAY_CALL_2',
                'way_code' => WayCodeEnum::ALI_QR,
                'amount' => 100,
                'subject' => '缺少描述',
            ],
        ]);
    }, '数组调用不能绕过必填字段校验');
};

$tests['log redaction'] = function () {
    $client = new ProtocolTestClient();
    $safe = $client->sanitized([
        'sign' => '1234567890ABCDEF',
        'md5_key' => 'never-log-this',
        'data' => ['pay_info' => 'payment-token-secret'],
        'channel_extra' => '{"openid":"openid-secret","buyer_id":"buyer-secret","auth_code":"auth-secret","div_list":[{"trans_in":"receiver-secret"}],"pay_data_type":"wxapp"}',
    ]);
    $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    foreach ([
        '1234567890ABCDEF',
        'never-log-this',
        'payment-token-secret',
        'openid-secret',
        'buyer-secret',
        'auth-secret',
        'receiver-secret',
    ] as $secret) {
        assertTrue(strpos($encoded, $secret) === false, "日志不得包含敏感值 $secret");
    }
    assertTrue(strpos($encoded, 'pay_data_type') !== false, '非敏感渠道字段应保留');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Unified order protocol: $passed/" . count($tests) . " passed\n";
