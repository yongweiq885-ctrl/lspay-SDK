<?php
/**
 * 退款订单查询协议回归测试。
 *
 * 运行：php php/tests/QueryRefundProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\QueryRefundRequest;
use LsPaySdk\response\QueryRefundResponse;

final class QueryRefundProtocolTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"success","data":{"order_refund_id":"R1","mch_refund_no":"MR1","state":0}}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }
}

function queryRefundAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function queryRefundAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function queryRefundAssertValidation(callable $callback, $message)
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

$tests['identifier validation'] = function () {
    (new QueryRefundRequest())->setMchRefundNo('MCH_REFUND_1')->validate();
    (new QueryRefundRequest())->setOrderRefundId('REFUND_ORDER_1')->validate();
    (new QueryRefundRequest())
        ->setMchRefundNo('MCH_REFUND_2')
        ->setOrderRefundId('REFUND_ORDER_2')
        ->validate();

    queryRefundAssertValidation(function () {
        (new QueryRefundRequest())->validate();
    }, '两个退款单号都为空时应拒绝');
    queryRefundAssertValidation(function () {
        (new QueryRefundRequest())
            ->setOrderRefundId(str_repeat('R', 31))
            ->validate();
    }, 'order_refund_id 超过 30 字符时应拒绝');
};

$tests['canonical request field'] = function () {
    $client = new QueryRefundProtocolTestClient();
    $response = $client->postRequest(
        (new QueryRefundRequest())->setOrderRefundId('REFUND_ORDER_3')
    );

    queryRefundAssertTrue(
        $response instanceof QueryRefundResponse,
        '应返回退款查询响应对象'
    );
    queryRefundAssertSame(
        'https://api.lspay.tech/gateway/refund/query',
        $client->capturedUrl,
        '退款查询 URL'
    );
    queryRefundAssertSame(
        'REFUND_ORDER_3',
        $client->capturedData['order_refund_id'],
        '应发送 order_refund_id'
    );
    queryRefundAssertTrue(
        !array_key_exists('refund_order_id', $client->capturedData),
        '兼容方法不得改变正式协议字段'
    );
    queryRefundAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
};

$tests['deprecated methods map to protocol field'] = function () {
    foreach (['setRefundOrderId', 'setRefundOrderNo'] as $method) {
        $client = new QueryRefundProtocolTestClient();
        $request = new QueryRefundRequest();
        $request->$method('REFUND_COMPAT');
        $client->postRequest($request);
        queryRefundAssertSame(
            'REFUND_COMPAT',
            $client->capturedData['order_refund_id'],
            "$method 应发送 order_refund_id"
        );
    }
};

$tests['typed response fields'] = function () {
    $client = new QueryRefundProtocolTestClient();
    $client->responseBody = json_encode([
        'status' => 200,
        'msg' => 'success',
        'data' => [
            'order_refund_id' => 'R100',
            'pay_order_id' => 'P100',
            'mch_no' => 'M100',
            'app_id' => 'A100',
            'mch_refund_no' => 'MR100',
            'pay_amount' => 58,
            'refund_amount' => 4,
            'currency' => 'cny',
            'state' => 2,
            'channel_order_no' => 'C100',
            'ext_param' => 'trace-100',
            'add_time' => 1623985552,
            'success_time' => 1623985554,
        ],
    ]);
    $response = $client->postRequest(
        (new QueryRefundRequest())->setMchRefundNo('MR100')
    );

    queryRefundAssertSame('R100', $response->getOrderRefundId(), '退款订单号');
    queryRefundAssertSame('P100', $response->getPayOrderId(), '支付订单号');
    queryRefundAssertSame('A100', $response->getAppId(), '应用 ID');
    queryRefundAssertSame(58, $response->getPayAmount(), '支付金额');
    queryRefundAssertSame(4, $response->getRefundAmount(), '退款金额');
    queryRefundAssertSame(1623985552, $response->getAddTime(), '10 位秒级时间戳');
    queryRefundAssertSame('trace-100', $response->getExtParam(), '扩展参数');
    queryRefundAssertTrue($response->isRefunded(), 'state=2 表示退款成功');
};

$tests['document field alias compatibility'] = function () {
    $client = new QueryRefundProtocolTestClient();
    $client->responseBody = '{"status":200,"msg":"success","data":{"order_refund_Id":"R-TYPO","channel_rrder_no":"C-TYPO","state":1}}';
    $response = $client->postRequest(
        (new QueryRefundRequest())->setMchRefundNo('MR-TYPO')
    );
    queryRefundAssertSame('R-TYPO', $response->getRefundOrderId(), '兼容 order_refund_Id');
    queryRefundAssertSame('C-TYPO', $response->getChannelOrderNo(), '兼容 channel_rrder_no');
    queryRefundAssertTrue(!$response->isRefunded(), 'state=1 尚未退款成功');
};

$tests['failed response'] = function () {
    $client = new QueryRefundProtocolTestClient();
    $client->responseBody = '{"status":400,"msg":"退款订单不存在","data":[]}';
    $response = $client->postRequest(
        (new QueryRefundRequest())->setMchRefundNo('MR-MISSING')
    );
    queryRefundAssertTrue($response->isError(), 'status=400 应为查询失败');
    queryRefundAssertTrue(!$response->isRefunded(), '查询失败不能视为退款成功');
};

$tests['compatible array call uses validation'] = function () {
    $client = new QueryRefundProtocolTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::QUERY_REFUND,
        'params' => ['order_refund_id' => 'ARRAY_REFUND_ORDER'],
    ]);
    queryRefundAssertTrue(
        $response instanceof QueryRefundResponse,
        '数组调用也应返回退款查询响应对象'
    );
    queryRefundAssertSame(
        'ARRAY_REFUND_ORDER',
        $client->capturedData['order_refund_id'],
        '数组调用字段'
    );

    queryRefundAssertValidation(function () use ($client) {
        $client->postRequest([
            'funcCode' => EndpointEnum::QUERY_REFUND,
            'params' => [],
        ]);
    }, '数组调用不能绕过退款单号校验');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Query refund protocol: $passed/" . count($tests) . " passed\n";
