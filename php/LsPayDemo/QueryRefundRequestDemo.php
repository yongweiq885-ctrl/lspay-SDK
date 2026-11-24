<?php
/**
 * 退款查询 - 示例
 *
 * 端点: /gateway/refund/query
 * 类:   \LsPaySdk\request\QueryRefundRequest
 */
namespace LsPayDemo;

require_once dirname(__FILE__) . '/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\QueryRefundRequest;

// 1. 组装请求参数（mch_refund_no / order_refund_id 至少填写一个）
$request = new QueryRefundRequest();
$request->setMchRefundNo('R20250101000001');
// 或者：
// $request->setOrderRefundId('RP2025010100001');

// 2. 发起接口调用
try {
    $client = new LsPayClient();
    $result = $client->postRequest($request);

    if (!$result || $result->isError()) {
        echo "退款查询失败: \n";
        var_dump($result ? $result->getErrorInfo() : 'null');
    } else {
        echo "退款查询成功（退款状态以 state 为准）: \n";
        var_dump($result->getData());
        echo $result->isRefunded() ? "退款成功\n" : "退款尚未完成\n";
    }
} catch (LsPayException $e) {
    echo "SDK 异常: " . $e->getMessage();
}
