<?php
/**
 * 延时分账订单转账与异步通知协议回归测试。
 *
 * 运行：php php/tests/DelayTransCreateTaskProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\core\LsPayTools;
use LsPaySdk\enums\DelayTransTaskStatusEnum;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPaySignException;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\notify\DelayTransTransferNotify;
use LsPaySdk\notify\LsPayNotify;
use LsPaySdk\request\DelayTransCreateTaskRequest;
use LsPaySdk\response\DelayTransCreateTaskResponse;

const TRANSFER_NOTIFY_TEST_KEY = 'transfer-notify-secret';

final class DelayTransCreateTaskTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"交易已创建","data":{"task_id":"TASK1","order_no":"TRANSFER1","account_no":"20160427210604000","account_name":"分销商","amount":100,"status":"PENDING","selected_orders":10,"first_order_time":"2025-07-29 14:05:10","last_order_time":"2025-07-29 14:05:10"}}';

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

function transferRequest()
{
    return (new DelayTransCreateTaskRequest())
        ->setAccountNo('20160427210604000')
        ->setAccountName('分销商')
        ->setOrderNo('TRANSFER1')
        ->setAmount(100)
        ->setNotifyUrl('https://merchant.test/delaytrans-notify')
        ->setRemark('测试转账');
}

function transferNotifyData($status = DelayTransTaskStatusEnum::PROCESSING)
{
    $data = [
        'task_id' => 'TASK_20250729_001',
        'mch_no' => 'TEST_MERCHANT',
        'app_id' => 'TEST_APP',
        'order_no' => 'TRANSFER1',
        'target_amount' => '10000',
        'processed_amount' => '6500',
        'remaining_amount' => '3500',
        'status' => $status,
        'progress' => '65.0',
        'completed_time' => null,
        'account_no' => '622588******1234',
        'account_name' => '张三科技有限公司',
        'remark' => 'Q3季度货款结算',
    ];
    if ($status === DelayTransTaskStatusEnum::PENDING
        || $status === DelayTransTaskStatusEnum::FAILED) {
        $data['processed_amount'] = '0';
        $data['remaining_amount'] = '10000';
        $data['progress'] = '0.0';
    }
    $data['sign'] = LsPayTools::sign($data, TRANSFER_NOTIFY_TEST_KEY);
    return $data;
}

function transferAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function transferAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function transferAssertException($class, callable $callback, $message)
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
    'md5_key' => TRANSFER_NOTIFY_TEST_KEY,
], true);

$tests = [];

$tests['request validation'] = function () {
    foreach ([
        (new DelayTransCreateTaskRequest())
            ->setAccountName('分销商')->setOrderNo('T1')->setAmount(100),
        (new DelayTransCreateTaskRequest())
            ->setAccountNo('A1')->setOrderNo('T1')->setAmount(100),
        (new DelayTransCreateTaskRequest())
            ->setAccountNo('A1')->setAccountName('分销商')->setAmount(100),
        transferRequest()->setAmount(0),
        transferRequest()->setNotifyUrl('javascript:alert(1)'),
        transferRequest()->setRemark(str_repeat('A', 257)),
    ] as $invalid) {
        transferAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '非法转账请求应被拒绝'
        );
    }
};

$tests['endpoint payload and typed response'] = function () {
    $client = new DelayTransCreateTaskTestClient();
    $response = $client->postRequest(transferRequest());

    transferAssertTrue(
        $response instanceof DelayTransCreateTaskResponse,
        '应返回延时分账订单转账响应对象'
    );
    transferAssertSame(
        'https://api.lspay.tech/gateway/delaytrans/createtask',
        $client->capturedUrl,
        '接口 URL'
    );
    transferAssertTrue(
        (bool)preg_match('/^\d{13}$/', $client->capturedData['req_time']),
        'req_time 应为 13 位毫秒时间戳'
    );
    transferAssertSame('20160427210604000', $client->capturedData['account_no'], '电子账号');
    transferAssertSame(100, $client->capturedData['amount'], '转账金额');
    transferAssertSame(200, $response->getStatus(), '公共响应状态');
    transferAssertSame('PENDING', $response->getTaskStatus(), '任务状态');
    transferAssertTrue($response->isCreated(), 'PENDING 表示任务已创建');
    transferAssertSame(10, $response->getSelectedOrders(), '使用订单数量');
};

$tests['compatible array call uses validation'] = function () {
    $client = new DelayTransCreateTaskTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::DELAY_TRANS_CREATE_TASK,
        'params' => [
            'account_no' => '20160427210604000',
            'account_name' => '分销商',
            'order_no' => 'TRANSFER_ARRAY',
            'amount' => 100,
        ],
    ]);
    transferAssertTrue(
        $response instanceof DelayTransCreateTaskResponse,
        '兼容数组调用也应返回类型化响应'
    );

    transferAssertException(
        LsPayValidationException::class,
        function () use ($client) {
            $client->postRequest([
                'funcCode' => EndpointEnum::DELAY_TRANS_CREATE_TASK,
                'params' => ['order_no' => 'INVALID_ONLY'],
            ]);
        },
        '兼容数组调用不能绕过必填字段校验'
    );
};

$tests['transfer notification parse'] = function () {
    $notify = LsPayNotify::parse(transferNotifyData());
    transferAssertTrue(
        $notify instanceof DelayTransTransferNotify,
        '应自动识别转账异步通知'
    );
    transferAssertSame(6500, $notify->getProcessedAmount(), '已处理金额');
    transferAssertSame(3500, $notify->getRemainingAmount(), '剩余金额');
    transferAssertSame(65.0, $notify->getProgress(), '处理进度');
    transferAssertTrue(!$notify->isSucceeded(), 'PROCESSING 尚未完成');
    transferAssertSame(
        'delaytrans-transfer:TASK_20250729_001:PROCESSING:6500',
        $notify->getIdempotencyKey(),
        '进度通知幂等键'
    );
    $notify->assertTask('TRANSFER1', 10000);
};

$tests['all notification fields participate in signature'] = function () {
    $data = transferNotifyData();
    unset($data['sign']);
    $data['future_field'] = 'future-value';
    $data['sign'] = LsPayTools::sign($data, TRANSFER_NOTIFY_TEST_KEY);
    $notify = LsPayNotify::parseDelayTransTransfer($data);
    transferAssertSame('future-value', $notify->getRaw()['future_field'], '未知字段应保留');

    $data['future_field'] = 'tampered';
    transferAssertException(
        LsPaySignException::class,
        function () use ($data) {
            LsPayNotify::parseDelayTransTransfer($data);
        },
        '篡改任意新增字段必须导致验签失败'
    );
};

$tests['notification status amount rules'] = function () {
    $pending = LsPayNotify::parseDelayTransTransfer(
        transferNotifyData(DelayTransTaskStatusEnum::PENDING)
    );
    transferAssertSame(0, $pending->getProcessedAmount(), '待处理金额规则');

    $invalid = transferNotifyData();
    unset($invalid['sign']);
    $invalid['remaining_amount'] = '3600';
    $invalid['sign'] = LsPayTools::sign($invalid, TRANSFER_NOTIFY_TEST_KEY);
    transferAssertException(
        LsPayValidationException::class,
        function () use ($invalid) {
            LsPayNotify::parseDelayTransTransfer($invalid);
        },
        '状态金额关系不合法应被拒绝'
    );
};

$tests['notification requires valid signature'] = function () {
    $unsigned = transferNotifyData();
    unset($unsigned['sign']);
    transferAssertException(
        LsPaySignException::class,
        function () use ($unsigned) {
            LsPayNotify::parseDelayTransTransfer($unsigned);
        },
        '异步通知缺少 sign 必须拒绝'
    );
};

$tests['sensitive transfer fields are redacted'] = function () {
    $client = new DelayTransCreateTaskTestClient();
    $safe = $client->sanitized([
        'account_no' => '20160427210604000',
        'account_name' => '张三科技有限公司',
    ]);
    $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE);
    transferAssertTrue(
        strpos($encoded, '20160427210604000') === false
            && strpos($encoded, '张三科技有限公司') === false,
        '日志不得包含完整账号或账户名'
    );
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Delay trans create task protocol: $passed/" . count($tests) . " passed\n";
