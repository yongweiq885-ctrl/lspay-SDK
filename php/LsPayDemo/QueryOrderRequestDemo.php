<?php
/**
 * 订单查询 - 示例
 *
 * 端点: /gateway/query
 * 类:   \LsPaySdk\request\QueryOrderRequest
 */
namespace LsPayDemo;

require_once dirname(__FILE__) . '/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\QueryOrderRequest;

// 1. 组装请求参数（mch_order_no / pay_order_id 至少填写一个）
$request = new QueryOrderRequest();
$request->setMchOrderNo('E20250101000001');
// 或者：
// $request->setPayOrderId('P2025010100001');

// 2. 发起接口调用
try {
    $client = new LsPayClient();
    $result = $client->postRequest($request);

    if (!$result || $result->isError()) {
        echo "查询失败: \n";
        var_dump($result ? $result->getErrorInfo() : 'null');
    } else {
        echo "查询成功（订单状态以 state 为准）: \n";
        var_dump($result->getData());
        echo $result->isPaid() ? "订单已支付\n" : "订单尚未支付\n";
    }
} catch (LsPayException $e) {
    echo "SDK 异常: " . $e->getMessage();
}
