<?php
/**
 * 余额支付协议回归测试。
 *
 * 运行：php php/tests/BalancePayProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\BalancePayChannelExtra;
use LsPaySdk\request\BalancePayRequest;
use LsPaySdk\request\DivisionReceiver;
use LsPaySdk\response\BalancePayResponse;

final class BalancePayTestClient extends LsPayClient
{
    public $capturedUrl = '';
    public $capturedData = [];
    public $responseBody = '{"status":200,"msg":"成功","data":{"err_msg":"交易成功","mch_order_no":"8504920400284465","pay_order_id":"8709543910489194496","amount":300,"order_state":2,"trans_status":"S"}}';

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

function balancePayExtra()
{
    return (new BalancePayChannelExtra())
        ->setTransOut('350873420280024268')
        ->addDivisionReceiver(
            (new DivisionReceiver('3709180455068368896'))->setDivAmt(100)
        )
        ->addDivisionReceiver(
            (new DivisionReceiver('3681144962280062976'))->setDivAmt(200)
        );
}

function balancePayRequest()
{
    return (new BalancePayRequest())
        ->setMchOrderNo('8504920400284465')
        ->setAmount(300)
        ->setCurrency('cny')
        ->setSubject('余额转账测试')
        ->setBody('余额转账测试')
        ->setNotifyUrl('https://www.lspay.tech/lspay/notify')
        ->setReturnUrl('https://www.lspay.tech/success')
        ->setChannelExtra(balancePayExtra());
}

function balancePayAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function balancePayAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function balancePayAssertException($class, callable $callback, $message)
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

$tests['typed balance channel extra'] = function () {
    $request = balancePayRequest();
    $request->validate();
    $extra = json_decode($request->getChannelExtra(), true);
    balancePayAssertSame('350873420280024268', $extra['trans_out'], '出款账户');
    balancePayAssertSame(2, count($extra['div_list']), '收款账户数量');
    balancePayAssertSame(300, array_sum(array_column($extra['div_list'], 'div_amt')), '转账总额');
};

$tests['fixed fields and transfer rules'] = function () {
    balancePayAssertSame(WayCodeEnum::BALANCE_PAY, balancePayRequest()->getWayCode(), '固定 way_code');
    balancePayAssertSame(1, balancePayRequest()->getDivisionMode(), '固定 division_mode');
    foreach ([
        balancePayRequest()->setWayCode(WayCodeEnum::ALI_QR),
        balancePayRequest()->setDivisionMode(0),
        balancePayRequest()->setChannelExtra([
            'div_list' => [['trans_in' => 'A', 'div_amt' => 100]],
        ]),
        balancePayRequest()->setChannelExtra([
            'trans_out' => 'OUT',
            'div_list' => [['trans_in' => 'A']],
        ]),
        balancePayRequest()->setAmount(299),
    ] as $invalid) {
        balancePayAssertException(
            LsPayValidationException::class,
            function () use ($invalid) { $invalid->validate(); },
            '余额支付固定字段或转账规则非法时应拒绝'
        );
    }
};

$tests['endpoint payload and typed response'] = function () {
    $client = new BalancePayTestClient();
    $response = $client->postRequest(balancePayRequest());

    balancePayAssertTrue($response instanceof BalancePayResponse, '应返回余额支付响应对象');
    balancePayAssertSame(
        'https://api.lspay.tech/gateway/unifiedorder',
        $client->capturedUrl,
        '接口 URL'
    );
    balancePayAssertSame('BALANCE_PAY', $client->capturedData['way_code'], '支付方式');
    balancePayAssertSame(1, $client->capturedData['division_mode'], '分账模式');
    balancePayAssertSame('8709543910489194496', $response->getPayOrderId(), '支付订单号');
    balancePayAssertTrue($response->isPaid(), 'order_state=2 应为支付成功');
};

$tests['compatible array call routes balance request'] = function () {
    $client = new BalancePayTestClient();
    $response = $client->postRequest([
        'funcCode' => EndpointEnum::UNIFIED_ORDER,
        'params' => [
            'mch_order_no' => 'BALANCE_ARRAY_1',
            'way_code' => 'BALANCE_PAY',
            'amount' => 100,
            'subject' => '余额支付',
            'body' => '余额支付',
            'division_mode' => 1,
            'channel_extra' => [
                'trans_out' => 'OUT_ACCOUNT',
                'div_list' => [['trans_in' => 'IN_ACCOUNT', 'div_amt' => 100]],
            ],
        ],
    ]);
    balancePayAssertTrue($response instanceof BalancePayResponse, '数组调用应路由余额支付');
};

$tests['balance account identifiers are redacted'] = function () {
    $client = new BalancePayTestClient();
    $safe = $client->sanitized([
        'channel_extra' => json_encode([
            'trans_out' => '350873420280024268',
            'div_list' => [['trans_in' => '3709180455068368896', 'div_amt' => 100]],
        ]),
    ]);
    $encoded = json_encode($safe);
    foreach (['350873420280024268', '3709180455068368896'] as $secret) {
        balancePayAssertTrue(
            strpos($encoded, $secret) === false,
            "日志不得包含余额账户编号 $secret"
        );
    }
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Balance pay protocol: $passed/" . count($tests) . " passed\n";
