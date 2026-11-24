<?php
/**
 * 支付与退款异步通知协议/安全回归测试。
 *
 * 运行：php php/tests/NotifyProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayTools;
use LsPaySdk\exceptions\LsPaySignException;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\notify\LsPayNotify;
use LsPaySdk\notify\PaymentNotify;
use LsPaySdk\notify\RefundNotify;

const NOTIFY_TEST_KEY = 'notify-test-secret';

function signedNotify(array $data)
{
    $data['sign'] = LsPayTools::sign($data, NOTIFY_TEST_KEY);
    return $data;
}

function paymentNotifyData()
{
    return signedNotify([
        'notify_type' => 'payment.succeeded',
        'pay_order_id' => '8835508652407259136',
        'mch_no' => 'TEST_MERCHANT',
        'app_id' => 'TEST_APP',
        'mch_order_no' => 'ORDER_100',
        'way_code' => 'WX_PUB',
        'amount' => '100',
        'currency' => 'cny',
        'state' => '2',
        'client_ip' => '117.141.171.91',
        'subject' => '测试商品',
        'body' => '支付测试',
        'channel_order_no' => 'CHANNEL_100',
        'ext_param' => '',
        'add_time' => '1790487185',
        'success_time' => '1790487200',
        'req_time' => '1790487185050',
        'event_type' => 'payment.succeeded',
    ]);
}

function refundNotifyData()
{
    return signedNotify([
        'notify_type' => 'refund',
        'pay_order_id' => '8835508652407259136',
        'mch_no' => 'TEST_MERCHANT',
        'app_id' => 'TEST_APP',
        'mch_refund_no' => 'REFUND_100',
        'way_code' => 'WX_PUB',
        'pay_amount' => '100',
        'refund_amount' => '40',
        'currency' => 'cny',
        'state' => '2',
        'ext_param' => '',
        'add_time' => '2026-09-27 13:40:08',
        'success_time' => '2026-09-27 13:40:09',
        'req_time' => '1790487608',
        'event_type' => 'refund.succeeded',
    ]);
}

function notifyAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function notifyAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

function notifyAssertException($class, callable $callback, $message)
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
    'md5_key' => NOTIFY_TEST_KEY,
], true);

$tests = [];

$tests['payment parse and assertions'] = function () {
    $notify = LsPayNotify::parse(paymentNotifyData());
    notifyAssertTrue($notify instanceof PaymentNotify, '应识别为支付通知');
    notifyAssertTrue($notify->isSucceeded(), 'state=2 与 payment.succeeded 表示支付成功');
    notifyAssertSame(100, $notify->getAmount(), '支付金额应转换为整数分');
    notifyAssertSame('ORDER_100', $notify->getMchOrderNo(), '商户订单号');
    notifyAssertSame(
        'payment:8835508652407259136:payment.succeeded',
        $notify->getIdempotencyKey(),
        '支付幂等键'
    );
    $notify->assertOrder('ORDER_100', 100, 'cny');
    notifyAssertException(
        LsPayValidationException::class,
        function () use ($notify) {
            $notify->assertOrder('ORDER_100', 99, 'cny');
        },
        '金额不一致必须拒绝'
    );
};

$tests['refund parse and assertions'] = function () {
    $notify = LsPayNotify::parse(refundNotifyData());
    notifyAssertTrue($notify instanceof RefundNotify, '应识别为退款通知');
    notifyAssertTrue($notify->isSucceeded(), 'state=2 与 refund.succeeded 表示退款成功');
    notifyAssertSame(100, $notify->getPayAmount(), '原支付金额');
    notifyAssertSame(40, $notify->getRefundAmount(), '退款金额');
    notifyAssertSame(
        'refund:REFUND_100:refund.succeeded',
        $notify->getIdempotencyKey(),
        '退款幂等键'
    );
    $notify->assertRefund('REFUND_100', 40, 'cny', 100);
};

$tests['all received fields participate in signature'] = function () {
    $data = paymentNotifyData();
    unset($data['sign']);
    $data['future_field'] = 'future-value';
    $data = signedNotify($data);
    $notify = LsPayNotify::parsePayment($data);
    notifyAssertSame(
        'future-value',
        $notify->getRaw()['future_field'],
        '新增字段应保留'
    );

    $data['future_field'] = 'tampered';
    notifyAssertException(
        LsPaySignException::class,
        function () use ($data) {
            LsPayNotify::parsePayment($data);
        },
        '篡改新增字段必须导致验签失败'
    );
};

$tests['invalid signature and identity'] = function () {
    $badSign = paymentNotifyData();
    $badSign['amount'] = '101';
    notifyAssertException(
        LsPaySignException::class,
        function () use ($badSign) {
            LsPayNotify::parsePayment($badSign);
        },
        '篡改金额必须导致验签失败'
    );

    $wrongMerchant = paymentNotifyData();
    unset($wrongMerchant['sign']);
    $wrongMerchant['mch_no'] = 'OTHER_MERCHANT';
    $wrongMerchant = signedNotify($wrongMerchant);
    notifyAssertException(
        LsPayValidationException::class,
        function () use ($wrongMerchant) {
            LsPayNotify::parsePayment($wrongMerchant);
        },
        '签名正确但商户身份不匹配仍必须拒绝'
    );
};

$tests['payload validation'] = function () {
    $nested = paymentNotifyData();
    unset($nested['sign']);
    $nested['future_field'] = ['not', 'scalar'];
    $nested = signedNotify($nested);
    notifyAssertException(
        LsPayValidationException::class,
        function () use ($nested) {
            LsPayNotify::parsePayment($nested);
        },
        'multipart 表单字段必须是标量'
    );

    $overRefund = refundNotifyData();
    unset($overRefund['sign']);
    $overRefund['refund_amount'] = '101';
    $overRefund = signedNotify($overRefund);
    notifyAssertException(
        LsPayValidationException::class,
        function () use ($overRefund) {
            LsPayNotify::parseRefund($overRefund);
        },
        '退款金额不能超过支付金额'
    );
};

$tests['failed refund remains valid notification'] = function () {
    $data = refundNotifyData();
    unset($data['sign']);
    $data['state'] = '3';
    $data['event_type'] = 'refund.failed';
    $data = signedNotify($data);
    $notify = LsPayNotify::parse($data);
    notifyAssertTrue(!$notify->isSucceeded(), '退款失败通知不应标记为退款成功');
    notifyAssertSame(3, $notify->getState(), '应保留退款失败状态');
};

$tests['global request and exact acknowledgement'] = function () {
    $oldServer = $_SERVER;
    $oldPost = $_POST;
    try {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=test';
        $_POST = paymentNotifyData();
        $notify = LsPayNotify::fromGlobals();
        notifyAssertTrue($notify instanceof PaymentNotify, '应从 multipart POST 解析');

        $_SERVER['CONTENT_TYPE'] = 'application/json';
        notifyAssertException(
            LsPayValidationException::class,
            function () {
                LsPayNotify::fromGlobals();
            },
            'JSON 通知必须拒绝'
        );
    } finally {
        $_SERVER = $oldServer;
        $_POST = $oldPost;
    }

    notifyAssertSame('ok', LsPayNotify::successResponse(), '成功应答必须精确为 ok');
    notifyAssertSame('fail', LsPayNotify::failureResponse(), '失败应答');
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Notify protocol: $passed/" . count($tests) . " passed\n";
