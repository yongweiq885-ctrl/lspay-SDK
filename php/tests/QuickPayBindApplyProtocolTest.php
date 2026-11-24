<?php
/**
 * 快捷支付绑卡申请协议回归测试。
 *
 * 运行：php php/tests/QuickPayBindApplyProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\QuickPayBindApplyRequest;
use LsPaySdk\request\QuickPayBindChannelExtra;
use LsPaySdk\response\QuickPayBindApplyResponse;

final class QuickPayBindApplyTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"处理成功","data":{"trans_status":"S","trans_id":"20160427210604000490","order_id":"BIND1","resp_desc":"成功"}}';

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

function bindChannelExtra()
{
    return (new QuickPayBindChannelExtra())
        ->setMchUserId('USER1')
        ->setCertType('00')
        ->setCardId('621467422001641112')
        ->setCardName('陈大海')
        ->setCertId('610621198404070414')
        ->setCertValidityType('0')
        ->setCertBeginDate('20170731')
        ->setCertEndDate('20370731')
        ->setCardMp('13324502906')
        ->setDcType('D');
}

function bindApplyRequest()
{
    return (new QuickPayBindApplyRequest())
        ->setOrderId('BIND1')
        ->setClientIp('127.0.0.1')
        ->setChannelExtra(bindChannelExtra());
}

function bindAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function bindAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function bindAssertException($class, callable $callback, $message)
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
    $request = bindApplyRequest();
    $request->validate();
    $extra = json_decode($request->getChannelExtra(), true);

    bindAssertSame('00', $extra['cert_type'], '证件类型默认值');
    bindAssertSame('D', $extra['dc_type'], '借记卡类型');
    bindAssertSame('621467422001641112', $extra['card_id'], '银行卡号');
};

$tests['required and fixed fields'] = function () {
    foreach ([
        new QuickPayBindApplyRequest(),
        (new QuickPayBindApplyRequest())->setOrderId('BIND1'),
        bindApplyRequest()->setClientIp('999.1.1.1'),
        bindApplyRequest()->setOrderId(str_repeat('A', 31)),
    ] as $invalid) {
        bindAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '绑卡申请必填字段或格式非法时应拒绝'
        );
    }
    bindAssertException(
        LsPayValidationException::class,
        function () {
            bindApplyRequest()->setWayCode(WayCodeEnum::BANK_FAST_JUMP);
        },
        'way_code 只能是 BANK_FAST'
    );
};

$tests['channel field validation'] = function () {
    $invalidDate = bindChannelExtra()->setCertBeginDate('20230230');
    $missingEndDate = bindChannelExtra()
        ->setCertValidityType('0')
        ->setCertEndDate('');
    $invalidExpiration = bindChannelExtra()->setExpiration('1328');
    $invalidDcType = bindChannelExtra()->setDcType('X');

    foreach ([$invalidDate, $missingEndDate, $invalidExpiration, $invalidDcType] as $extra) {
        bindAssertException(
            LsPayValidationException::class,
            function () use ($extra) { $extra->toArray(); },
            '非法绑卡渠道字段应拒绝'
        );
    }
};

$tests['endpoint payload and wrapped response'] = function () {
    $client = new QuickPayBindApplyTestClient();
    $response = $client->postRequest(bindApplyRequest());

    bindAssertTrue(
        $response instanceof QuickPayBindApplyResponse,
        '应返回绑卡申请响应对象'
    );
    bindAssertSame(
        'https://api.lspay.tech/gateway/quickpay/buckleapply',
        $client->capturedUrl,
        '接口 URL'
    );
    bindAssertSame(WayCodeEnum::BANK_FAST, $client->capturedData['way_code'], '固定支付方式');
    bindAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    bindAssertTrue($response->isSuccessful(), 'trans_status=S 应为成功');
    bindAssertSame('20160427210604000490', $response->getTransId(), '交易流水号');
};

$tests['flat response compatibility'] = function () {
    $success = new QuickPayBindApplyResponse([
        'trans_status' => 'S',
        'trans_id' => 'TRANS1',
        'order_id' => 'BIND1',
        'resp_desc' => '成功',
    ]);
    bindAssertTrue(!$success->isError(), '官方直接业务字段响应应兼容');
    bindAssertTrue($success->isSuccessful(), '平铺 trans_status=S 应为成功');

    $processing = new QuickPayBindApplyResponse([
        'trans_status' => 'P',
        'trans_id' => 'TRANS2',
        'order_id' => 'BIND2',
        'resp_desc' => '处理中',
    ]);
    bindAssertTrue($processing->isProcessing(), 'trans_status=P 应为处理中');

    $failed = new QuickPayBindApplyResponse([
        'trans_status' => 'F',
        'resp_desc' => '失败',
    ]);
    bindAssertTrue($failed->isError(), '平铺 trans_status=F 应为失败');
};

$tests['compatible array call uses validation'] = function () {
    $client = new QuickPayBindApplyTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::QUICK_PAY_BIND_APPLY,
        'params' => [
            'order_id' => 'BIND_ARRAY',
            'way_code' => WayCodeEnum::BANK_FAST,
            'channel_extra' => bindChannelExtra()->toArray(),
        ],
    ]);
    bindAssertTrue(
        $response instanceof QuickPayBindApplyResponse,
        '兼容数组调用也应返回类型化响应'
    );

    bindAssertException(
        LsPayValidationException::class,
        function () use ($client) {
            $client->postRequest([
                'funcCode' => EndpointEnum::QUICK_PAY_BIND_APPLY,
                'params' => ['order_id' => 'BIND_INVALID'],
            ]);
        },
        '兼容数组调用不能绕过 channel_extra 校验'
    );
};

$tests['sensitive card fields are redacted'] = function () {
    $client = new QuickPayBindApplyTestClient();
    $safe = $client->sanitized([
        'channel_extra' => bindChannelExtra()->toJson(),
    ]);
    $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE);
    foreach ([
        'USER1',
        '621467422001641112',
        '陈大海',
        '610621198404070414',
        '20170731',
        '20370731',
        '13324502906',
    ] as $secret) {
        bindAssertTrue(
            strpos($encoded, $secret) === false,
            "日志不得包含绑卡敏感值 $secret"
        );
    }
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Quick pay bind apply protocol: $passed/" . count($tests) . " passed\n";
