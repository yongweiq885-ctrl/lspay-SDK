<?php
/**
 * 电子账户结算记录查询协议回归测试。
 *
 * 运行：php php/tests/ElectronicAccountSettlementQueryProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\ElectronicAccountSettlementQueryRequest;
use LsPaySdk\response\ElectronicAccountSettlementQueryResponse;

final class ElectronicAccountSettlementQueryTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"成功","data":{"trans_id":"1000335187840801","trans_date":"20220125","settle_cycle":"T1","card_no":"622262****8994","card_name":"张三","bank_code":"01040000","trans_amt":"1.00","fee_amt":"0.50","trans_stat":"S","settle_type":"0","settle_abstract":"货款","settle_batch_no":"100","settle_desc":""}}';

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

function settlementQueryRequest()
{
    return (new ElectronicAccountSettlementQueryRequest())
        ->setOrderId('SETTLE202504010001')
        ->setAccountNo('35087342028002411688')
        ->setBeginDate('20250401')
        ->setEndDate('20250409')
        ->setPageSize(10)
        ->setPageNum(1);
}

function settlementQueryAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function settlementQueryAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function settlementQueryAssertException($class, callable $callback, $message)
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

$tests['fixed fields and request validation'] = function () {
    settlementQueryAssertSame(
        'SETTLE_QUERY',
        settlementQueryRequest()->getWayCode(),
        'way_code 默认固定值'
    );
    foreach ([
        (new ElectronicAccountSettlementQueryRequest()),
        settlementQueryRequest()->setWayCode('OTHER'),
        settlementQueryRequest()->setBeginDate('20250230'),
        settlementQueryRequest()->setBeginDate('20250501')->setEndDate('20250401'),
        settlementQueryRequest()->setBeginDate('20250401')->setEndDate('20250502'),
        settlementQueryRequest()->setPageSize(0),
        settlementQueryRequest()->setPageNum('1.5'),
    ] as $invalid) {
        settlementQueryAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '结算查询必填或格式非法时应拒绝'
        );
    }
    settlementQueryRequest()
        ->setBeginDate('20250401')
        ->setEndDate('20250501')
        ->validate();
};

$tests['endpoint payload and typed response'] = function () {
    $client = new ElectronicAccountSettlementQueryTestClient();
    $response = $client->postRequest(settlementQueryRequest());

    settlementQueryAssertTrue(
        $response instanceof ElectronicAccountSettlementQueryResponse,
        '应返回结算记录查询响应对象'
    );
    settlementQueryAssertSame(
        'https://api.lspay.tech/gateway/user/settlement/query',
        $client->capturedUrl,
        '接口 URL'
    );
    settlementQueryAssertSame('SETTLE_QUERY', $client->capturedData['way_code'], '支付方式');
    settlementQueryAssertSame('20250401', $client->capturedData['begin_date'], '开始日期');
    settlementQueryAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    settlementQueryAssertSame('1000335187840801', $response->getTransId(), '结算流水号');
    settlementQueryAssertSame('1.00', $response->getTransAmount(), '结算金额');
    settlementQueryAssertSame('S', $response->getTransStatus(), '结算状态');
};

$tests['list response compatibility'] = function () {
    $client = new ElectronicAccountSettlementQueryTestClient();
    $client->responseBody = '{"status":200,"msg":"成功","data":{"records":[{"trans_id":"A"},{"trans_id":"B"}],"total":2}}';
    $response = $client->postRequest(settlementQueryRequest());
    settlementQueryAssertSame(2, count($response->getRecords()), '应读取 records 分页列表');
    settlementQueryAssertSame('A', $response->getTransId(), '便捷 getter 读取第一条记录');
};

$tests['compatible array call'] = function () {
    $client = new ElectronicAccountSettlementQueryTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY,
        'params' => [
            'order_id' => 'SETTLE_ARRAY_1',
            'account_no' => 'ACCOUNT_ARRAY',
            'begin_date' => '20250401',
            'end_date' => '20250409',
        ],
    ]);
    settlementQueryAssertTrue(
        $response instanceof ElectronicAccountSettlementQueryResponse,
        '兼容数组调用也应返回类型化响应'
    );
    settlementQueryAssertSame(
        'SETTLE_QUERY',
        $client->capturedData['way_code'],
        '数组调用应补齐固定 way_code'
    );
};

$tests['settlement identity fields are redacted'] = function () {
    $client = new ElectronicAccountSettlementQueryTestClient();
    $safe = $client->sanitized([
        'account_no' => '35087342028002411688',
        'data' => [
            'card_no' => '6222621234568994',
            'card_name' => '张三',
        ],
    ]);
    $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE);
    foreach (['35087342028002411688', '6222621234568994', '张三'] as $secret) {
        settlementQueryAssertTrue(
            strpos($encoded, $secret) === false,
            "日志不得包含结算查询敏感值 $secret"
        );
    }
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Electronic account settlement query protocol: $passed/"
    . count($tests) . " passed\n";
