<?php
/**
 * 订单查询协议回归测试。
 *
 * 运行：php php/tests/QueryOrderProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\QueryOrderRequest;
use LsPaySdk\response\QueryOrderResponse;

final class QueryProtocolTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"success","data":{"pay_order_id":"P1","mch_order_no":"M1","state":2}}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }
}

function queryAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function queryAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function queryAssertValidation(callable $callback, $message)
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
    (new QueryOrderRequest())->setMchOrderNo('MCH_ORDER_1')->validate();
    (new QueryOrderRequest())->setPayOrderId('PAY_ORDER_1')->validate();
    (new QueryOrderRequest())
        ->setMchOrderNo('MCH_ORDER_2')
        ->setPayOrderId('PAY_ORDER_2')
        ->validate();

    queryAssertValidation(function () {
        (new QueryOrderRequest())->validate();
    }, '两个订单号都为空时应拒绝');

    queryAssertValidation(function () {
        (new QueryOrderRequest())->setPayOrderId(str_repeat('P', 31))->validate();
    }, 'pay_order_id 超过 30 字符时应拒绝');
};

$tests['canonical request field'] = function () {
    $client = new QueryProtocolTestClient();
    $response = $client->postRequest(
        (new QueryOrderRequest())->setPayOrderId('PAY_ORDER_3')
    );

    queryAssertTrue($response instanceof QueryOrderResponse, '应返回订单查询响应对象');
    queryAssertSame('https://api.lspay.tech/gateway/query', $client->capturedUrl, '查询 URL');
    queryAssertSame('PAY_ORDER_3', $client->capturedData['pay_order_id'], '应发送 pay_order_id');
    queryAssertTrue(
        !array_key_exists('pay_order_no', $client->capturedData),
        '兼容方法不得改变网关协议字段 pay_order_id'
    );
    queryAssertTrue((bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']), 'req_time 应为 13 位毫秒时间戳');
};

$tests['deprecated method maps to canonical field'] = function () {
    $client = new QueryProtocolTestClient();
    $client->postRequest(
        (new QueryOrderRequest())->setPayOrderNo('PAY_ORDER_LEGACY_METHOD')
    );
    queryAssertSame(
        'PAY_ORDER_LEGACY_METHOD',
        $client->capturedData['pay_order_id'],
        '旧方法也必须发送 pay_order_id'
    );
};

$tests['typed response fields'] = function () {
    $client = new QueryProtocolTestClient();
    $client->responseBody = json_encode([
        'status' => 200,
        'msg' => 'success',
        'data' => [
            'pay_order_id' => 'P100',
            'mch_no' => 'M100',
            'app_Id' => 'A100',
            'mch_order_no' => 'O100',
            'way_code' => 'WX_PUB',
            'amount' => 58,
            'currency' => 'cny',
            'state' => 2,
            'client_ip' => '127.0.0.1',
            'subject' => '商品',
            'body' => '描述',
            'channel_order_no' => 'C100',
            'ext_param' => 'trace-1',
            'add_time' => 1623985457,
            'success_time' => 1623985459,
            'mch_fee_amount      ' => 3,
            'division_mode' => 1,
            'division_state' => 3,
            'unconfirm_amount' => 10,
            'confirmed_amount' => 48,
            'fund_freeze_stat' => 'FREEZE',
            'freeze_time' => '20091225091010',
            'unfreeze_amt' => '12',
            'unfreeze_time' => '20091226091010',
        ],
    ]);

    $response = $client->postRequest(
        (new QueryOrderRequest())->setMchOrderNo('O100')
    );
    queryAssertSame('A100', $response->getAppId(), '兼容读取文档中的 app_Id');
    queryAssertSame(58, $response->getAmount(), '金额应为整数分');
    queryAssertSame(2, $response->getState(), '订单状态');
    queryAssertSame(3, $response->getMchFeeAmount(), '兼容读取示例中带空格的手续费字段');
    queryAssertSame(1623985457, $response->getAddTime(), '10 位秒级时间戳');
    queryAssertSame('12', $response->getUnfreezeAmount(), '按协议保留解冻金额字符串');
    queryAssertTrue($response->isPaid(), 'state=2 应为已支付');
    queryAssertTrue($response->isFundFrozen(), 'FREEZE 应为资金冻结');
};

$tests['response failure and unpaid state'] = function () {
    $client = new QueryProtocolTestClient();
    $client->responseBody = '{"status":200,"msg":"success","data":{"state":0}}';
    $unpaid = $client->postRequest(
        (new QueryOrderRequest())->setMchOrderNo('ORDER_UNPAID')
    );
    queryAssertTrue(!$unpaid->isPaid(), 'state=0 不代表支付成功');

    $client->responseBody = '{"status":400,"msg":"订单不存在"}';
    $missing = $client->postRequest(
        (new QueryOrderRequest())->setMchOrderNo('ORDER_MISSING')
    );
    queryAssertTrue($missing->isError(), 'status=400 应为查询失败');
    queryAssertSame('订单不存在', $missing->getMsg(), '应保留错误消息');
};

$tests['compatible array call uses validation'] = function () {
    $client = new QueryProtocolTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::QUERY_ORDER,
        'params' => ['pay_order_id' => 'ARRAY_PAY_ORDER'],
    ]);
    queryAssertTrue($response instanceof QueryOrderResponse, '数组调用也应返回查询响应对象');
    queryAssertSame('ARRAY_PAY_ORDER', $client->capturedData['pay_order_id'], '数组调用字段');

    queryAssertValidation(function () use ($client) {
        $client->postRequest([
            'funcCode' => EndpointEnum::QUERY_ORDER,
            'params' => [],
        ]);
    }, '数组调用不能绕过订单号校验');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Query order protocol: $passed/" . count($tests) . " passed\n";
