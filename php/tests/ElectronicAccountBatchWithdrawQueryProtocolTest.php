<?php
/**
 * 电子账户批量提现查询协议回归测试。
 *
 * 运行：php php/tests/ElectronicAccountBatchWithdrawQueryProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\ElectronicAccountBatchWithdrawQueryRequest;
use LsPaySdk\response\ElectronicAccountBatchWithdrawQueryResponse;

final class ElectronicAccountBatchWithdrawQueryTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"success","data":{"count":1,"page_size":15,"page_num":1,"data":[{"bank_name":"","batch_trans_type":"ENCHASHMENT","card_name":"陈大海","card_no":"621467*********1530","cash_amt":"11105.44","fee_amt":"5.56","fee_flag":"2","org_req_date":"20250422","remark":"","trans_desc":"交易失败,出账失败,账户可用余额不足","trans_status":"failed","settle_date":"20250422","order_id":"12"}],"status":"succeess"}}';

    protected function curlRequest($url, array $data)
    {
        $this->capturedUrl = $url;
        $this->capturedData = $data;
        return $this->responseBody;
    }
}

function batchWithdrawQueryRequest()
{
    return (new ElectronicAccountBatchWithdrawQueryRequest())
        ->setAccountNo('2123214353453456564')
        ->setBeginDate('20250401')
        ->setEndDate('20250409')
        ->setPageSize(10)
        ->setPageNum(1);
}

function batchWithdrawQueryAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function batchWithdrawQueryAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function batchWithdrawQueryAssertException($class, callable $callback, $message)
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
    batchWithdrawQueryAssertSame(
        'WITHDRAW_QUERY',
        batchWithdrawQueryRequest()->getWayCode(),
        'way_code 默认固定值'
    );
    foreach ([
        new ElectronicAccountBatchWithdrawQueryRequest(),
        batchWithdrawQueryRequest()->setWayCode('OTHER'),
        batchWithdrawQueryRequest()->setBeginDate('20250230'),
        batchWithdrawQueryRequest()->setBeginDate('20250501')->setEndDate('20250401'),
        batchWithdrawQueryRequest()->setPageSize(0),
        batchWithdrawQueryRequest()->setPageNum('1.5'),
    ] as $invalid) {
        batchWithdrawQueryAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '批量提现查询必填或格式非法时应拒绝'
        );
    }

    // 官方示例跨度超过文字所述 3 天，SDK 保留示例兼容性。
    batchWithdrawQueryRequest()->validate();
};

$tests['endpoint payload and paged response'] = function () {
    $client = new ElectronicAccountBatchWithdrawQueryTestClient();
    $response = $client->postRequest(batchWithdrawQueryRequest());

    batchWithdrawQueryAssertTrue(
        $response instanceof ElectronicAccountBatchWithdrawQueryResponse,
        '应返回批量提现查询响应对象'
    );
    batchWithdrawQueryAssertSame(
        'https://api.lspay.tech/gateway/user/balance/withdraw/query',
        $client->capturedUrl,
        '接口 URL'
    );
    batchWithdrawQueryAssertSame('WITHDRAW_QUERY', $client->capturedData['way_code'], '支付方式');
    batchWithdrawQueryAssertSame('20250401', $client->capturedData['begin_date'], '开始日期');
    batchWithdrawQueryAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    batchWithdrawQueryAssertSame(1, $response->getCount(), '总条数');
    batchWithdrawQueryAssertSame(15, $response->getPageSize(), '响应分页大小');
    batchWithdrawQueryAssertSame(1, count($response->getRecords()), '记录数量');
    batchWithdrawQueryAssertSame('11105.44', $response->getCashAmount(), '提现金额');
    batchWithdrawQueryAssertTrue($response->isChannelSuccessful(), '识别接口特有 succeess 成功状态');
    batchWithdrawQueryAssertTrue($response->isFirstRecordFailed(), '首条 failed 状态');
};

$tests['record status semantics'] = function () {
    foreach ([
        'pending' => 'isFirstRecordPending',
        'failed' => 'isFirstRecordFailed',
        'succeeded' => 'isFirstRecordSucceeded',
    ] as $status => $method) {
        $response = new ElectronicAccountBatchWithdrawQueryResponse([
            'status' => 200,
            'msg' => 'success',
            'data' => ['data' => [['trans_status' => $status]]],
        ]);
        batchWithdrawQueryAssertTrue($response->$method(), "$status 状态判断");
    }
    $corrected = new ElectronicAccountBatchWithdrawQueryResponse([
        'status' => 200,
        'data' => ['status' => 'success', 'data' => []],
    ]);
    batchWithdrawQueryAssertTrue($corrected->isChannelSuccessful(), '兼容相关接口 success 成功状态');
};

$tests['gateway failure'] = function () {
    $client = new ElectronicAccountBatchWithdrawQueryTestClient();
    $client->responseBody = '{"status":400,"msg":"参数错误","data":[]}';
    $response = $client->postRequest(batchWithdrawQueryRequest());
    batchWithdrawQueryAssertTrue($response->isError(), 'status=400 应为失败');
    batchWithdrawQueryAssertSame([], $response->getRecords(), '失败时记录列表为空');
};

$tests['compatible array call'] = function () {
    $client = new ElectronicAccountBatchWithdrawQueryTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY,
        'params' => [
            'account_no' => 'ACCOUNT_ARRAY',
            'begin_date' => '20250401',
            'end_date' => '20250402',
        ],
    ]);
    batchWithdrawQueryAssertTrue(
        $response instanceof ElectronicAccountBatchWithdrawQueryResponse,
        '兼容数组调用也应返回类型化响应'
    );
    batchWithdrawQueryAssertSame(
        'WITHDRAW_QUERY',
        $client->capturedData['way_code'],
        '数组调用应补齐固定 way_code'
    );
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Electronic account batch withdraw query protocol: $passed/"
    . count($tests) . " passed\n";
