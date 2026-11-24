<?php
/**
 * 申请退款协议回归测试。
 *
 * 运行：php php/tests/RefundProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\RefundRequest;
use LsPaySdk\response\RefundResponse;

final class RefundProtocolTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"success","data":{"refund_order_id":"R1","mch_refund_no":"MR1","refund_amount":1,"pay_amount":100,"state":0},"sign":"UNVERIFIED_RESPONSE_SIGN"}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }
}

function refundValidRequest()
{
    return (new RefundRequest())
        ->setMchRefundNo('REFUND_' . time())
        ->setMchOrderNo('ORDER_1')
        ->setRefundAmount(1)
        ->setCurrency('cny')
        ->setRefundReason('用户退货');
}

function refundAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function refundAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function refundAssertValidation(callable $callback, $message)
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

$tests['required fields and identifiers'] = function () {
    refundValidRequest()->validate();
    refundValidRequest()
        ->setMchOrderNo('')
        ->setPayOrderId('PAY_ORDER_1')
        ->validate();

    refundAssertValidation(function () {
        refundValidRequest()
            ->setMchOrderNo('')
            ->setPayOrderId('')
            ->validate();
    }, '支付订单号至少填写一个');
    refundAssertValidation(function () {
        refundValidRequest()->setMchRefundNo('')->validate();
    }, '商户退款单号必填');
    refundAssertValidation(function () {
        refundValidRequest()->setRefundReason('')->validate();
    }, '退款原因必填');
    refundAssertValidation(function () {
        refundValidRequest()->setRefundAmount(0)->validate();
    }, '退款金额必须大于 0');
    refundAssertValidation(function () {
        refundValidRequest()->setRefundAmount('1.5');
    }, '退款金额必须是整数分');
};

$tests['length network and json validation'] = function () {
    refundAssertValidation(function () {
        refundValidRequest()->setRefundReason(str_repeat('R', 65))->validate();
    }, '退款原因不能超过 64 字符');
    refundAssertValidation(function () {
        refundValidRequest()->setClientIp('999.1.1.1')->validate();
    }, 'client_ip 必须为 IPv4');
    refundAssertValidation(function () {
        refundValidRequest()->setNotifyUrl('ftp://merchant.test/refund')->validate();
    }, 'notify_url 只允许 HTTP(S)');
    refundAssertValidation(function () {
        refundValidRequest()->setChannelExtra('[1,2]');
    }, 'channel_extra 不允许 JSON 列表');

    $request = refundValidRequest()
        ->setChannelExtra(['route' => 'FAST'])
        ->setExtParam(['trace' => 'refund-1']);
    $request->validate();
    refundAssertSame('{"route":"FAST"}', $request->getChannelExtra(), '渠道参数规范化');
    refundAssertSame('{"trace":"refund-1"}', $request->getExtParam(), '扩展参数 JSON 化');
};

$tests['canonical request payload'] = function () {
    $client = new RefundProtocolTestClient();
    $response = $client->postRequest(
        refundValidRequest()
            ->setPayOrderId('PAY_ORDER_2')
            ->setClientIp('127.0.0.1')
            ->setNotifyUrl('https://merchant.test/refund')
            ->setExtParam('trace-2')
    );

    refundAssertTrue($response instanceof RefundResponse, '应返回申请退款响应对象');
    refundAssertSame('https://api.lspay.tech/gateway/refund', $client->capturedUrl, '退款 URL');
    refundAssertSame('PAY_ORDER_2', $client->capturedData['pay_order_id'], '发送 pay_order_id');
    refundAssertSame(1, $client->capturedData['refund_amount'], '退款金额单位为分');
    refundAssertSame('cny', $client->capturedData['currency'], '币种');
    refundAssertTrue(!array_key_exists('amount', $client->capturedData), '不得发送文档未定义的 amount');
    refundAssertTrue(!array_key_exists('pay_amount', $client->capturedData), '请求不得发送响应字段 pay_amount');
    refundAssertTrue((bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']), 'req_time 为 13 位毫秒时间戳');
};

$tests['deprecated order method maps to protocol field'] = function () {
    $client = new RefundProtocolTestClient();
    $client->postRequest(
        refundValidRequest()
            ->setMchOrderNo('')
            ->setPayOrderNo('PAY_ORDER_COMPAT')
    );
    refundAssertSame(
        'PAY_ORDER_COMPAT',
        $client->capturedData['pay_order_id'],
        '兼容方法仍发送 pay_order_id'
    );
};

$tests['typed response semantics'] = function () {
    $client = new RefundProtocolTestClient();
    $client->responseBody = '{"status":200,"msg":"success","data":{"channel_order_no":"C1","mch_refund_no":"MR1","pay_amount":58,"refund_amount":4,"refund_order_id":"R1","state":2},"sign":"ARBITRARY_SIGN"}';
    $response = $client->postRequest(refundValidRequest());

    refundAssertSame('R1', $response->getRefundOrderId(), '退款订单号');
    refundAssertSame(58, $response->getPayAmount(), '原支付金额');
    refundAssertSame(4, $response->getRefundAmount(), '退款金额');
    refundAssertSame('ARBITRARY_SIGN', $response->getResponseSign(), '保留同步响应 sign');
    refundAssertTrue($response->isAccepted(), 'status=200 表示接口受理');
    refundAssertTrue($response->isRefunded(), 'state=2 表示退款成功');
};

$tests['pending and failed responses'] = function () {
    $client = new RefundProtocolTestClient();
    $pending = $client->postRequest(refundValidRequest());
    refundAssertTrue($pending->isAccepted(), 'state=0 时接口仍可已受理');
    refundAssertTrue(!$pending->isRefunded(), 'state=0 不是退款成功');

    $client->responseBody = '{"status":400,"msg":"退款订单已存在","data":[]}';
    $failed = $client->postRequest(refundValidRequest());
    refundAssertTrue($failed->isError(), 'status=400 应为退款申请失败');
    refundAssertTrue(!$failed->isRefunded(), '失败响应不能视为退款成功');
};

$tests['compatible array call uses validation'] = function () {
    $client = new RefundProtocolTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::REFUND,
        'params' => [
            'mch_refund_no' => 'ARRAY_REFUND_1',
            'pay_order_id' => 'ARRAY_PAY_1',
            'refund_amount' => 1,
            'currency' => 'cny',
            'refund_reason' => '数组调用退款',
        ],
    ]);
    refundAssertTrue($response instanceof RefundResponse, '数组调用也应返回退款响应对象');

    refundAssertValidation(function () use ($client) {
        $client->postRequest([
            'funcCode' => EndpointEnum::REFUND,
            'params' => [
                'mch_refund_no' => 'ARRAY_REFUND_2',
                'refund_amount' => 1,
                'refund_reason' => '缺少原订单号',
            ],
        ]);
    }, '数组调用不能绕过原订单号校验');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Refund protocol: $passed/" . count($tests) . " passed\n";
