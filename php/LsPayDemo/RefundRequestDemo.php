<?php
/**
 * 申请退款 - 示例
 *
 * 端点: /gateway/refund
 * 类:   \LsPaySdk\request\RefundRequest
 */
namespace LsPayDemo;

require_once dirname(__FILE__) . '/loader.php';

use LsPayDemo\constants\DemoConstants;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\core\LsPayTools;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\RefundRequest;

// 1. 组装请求参数
$request = new RefundRequest();
$request->setMchRefundNo(DemoConstants::buildRefundNo())
        ->setMchOrderNo('E20250101000001')               // 原支付订单号
        ->setRefundAmount(LsPayTools::toFen('0.01'))     // 0.01 元 → 1 分
        ->setCurrency('cny')
        ->setRefundReason('用户取消')
        ->setClientIp('127.0.0.1')
        ->setExtParam('refund-demo')
        ->setNotifyUrl('https://your.domain/refund_notify.php');

// 2. 发起接口调用
try {
    $client = new LsPayClient();
    $result = $client->postRequest($request);

    if (!$result || $result->isError()) {
        echo "退款失败: \n";
        var_dump($result ? $result->getErrorInfo() : 'null');
    } else {
        echo "退款申请接口调用成功（是否完成以 state=2 为准）: \n";
        var_dump($result->getData());
        echo $result->isRefunded() ? "退款成功\n" : "退款处理中\n";
    }
} catch (LsPayException $e) {
    echo "SDK 异常: " . $e->getMessage();
}
