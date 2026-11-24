<?php
/**
 * 支付 / 退款异步通知安全处理骨架。
 *
 * 平台使用 multipart/form-data POST。只有完成验签、订单核对和幂等落库后，
 * 才能返回精确字符串 "ok"；否则返回 "fail"，平台会按规则重试。
 */
namespace LsPayDemo;

require_once dirname(__FILE__) . '/loader.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\notify\DelayTransTransferNotify;
use LsPaySdk\notify\LsPayNotify;
use LsPaySdk\notify\PaymentNotify;
use LsPaySdk\notify\RefundNotify;

header('Content-Type: text/plain; charset=utf-8');

try {
    // 多商户场景请由独立回调 URL 映射固定 profile，不能直接信任请求参数选密钥。
    $notify = LsPayNotify::fromGlobals('default');

    if ($notify instanceof PaymentNotify) {
        $handled = handlePayment($notify);
    } elseif ($notify instanceof RefundNotify) {
        $handled = handleRefund($notify);
    } elseif ($notify instanceof DelayTransTransferNotify) {
        $handled = handleDelayTransTransfer($notify);
    } else {
        $handled = false;
    }

    if (!$handled) {
        throw new \RuntimeException('业务处理未完成');
    }

    LsPay::writeLog(
        'NOTIFY_ACCEPTED type=' . get_class($notify)
        . ' idempotency_key=' . $notify->getIdempotencyKey()
    );
    http_response_code(200);
    exit(LsPayNotify::successResponse());
} catch (\Throwable $e) {
    LsPay::writeLog(
        'NOTIFY_REJECTED type=' . get_class($e)
        . ' message=' . $e->getMessage(),
        'ERROR'
    );
    http_response_code(500);
    exit(LsPayNotify::failureResponse());
}

function handlePayment(PaymentNotify $notify)
{
    if (!$notify->isSucceeded()) {
        return false;
    }

    // 必须替换为真实数据库事务：
    // 1. 按 mch_order_no 查询本地订单并锁行；
    // 2. 调用 $notify->assertOrder($orderNo, $amountFen, $currency)；
    // 3. 以 getIdempotencyKey() 或 pay_order_id + event_type 建唯一键去重；
    // 4. 已成功处理的重复通知直接返回 true；
    // 5. 更新支付状态并提交事务后才返回 true。
    return false;
}

function handleRefund(RefundNotify $notify)
{
    // 必须替换为真实数据库事务：
    // 1. 按 mch_refund_no 查询退款记录并锁行；
    // 2. 调用 $notify->assertRefund($refundNo, $refundFen, $currency, $payFen)；
    // 3. 以 getIdempotencyKey() 建唯一键去重；
    // 4. 根据 state 更新成功或失败状态；
    // 5. 已处理的重复通知返回 true，提交事务后才返回 true。
    return false;
}

function handleDelayTransTransfer(DelayTransTransferNotify $notify)
{
    // 必须替换为真实数据库事务：
    // 1. 按 order_no 查询转账任务并锁行；
    // 2. 调用 $notify->assertTask($orderNo, $amountFen)；
    // 3. 以 getIdempotencyKey() 建唯一键，允许同一任务按进度多次通知；
    // 4. 保存 processed_amount / remaining_amount / progress / status；
    // 5. 提交事务后才返回 true；重复通知也应安全返回 true。
    return false;
}
