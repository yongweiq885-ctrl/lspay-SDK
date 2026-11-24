<?php
/**
 * 统一下单 - 示例
 *
 * 端点: /gateway/unifiedorder
 * 类:   \LsPaySdk\request\UnifiedOrderRequest
 */
namespace LsPayDemo;

require_once dirname(__FILE__) . '/loader.php';

use LsPayDemo\constants\DemoConstants;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\PayDataTypeEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\UnifiedOrderChannelExtra;
use LsPaySdk\request\UnifiedOrderRequest;

// 1. 组装请求参数
$channelExtra = (new UnifiedOrderChannelExtra())
    ->setPayDataType(PayDataTypeEnum::PAY_URL);   // cashier 收银台见 v1/payment/create/CashierFormDemo.php

$request = new UnifiedOrderRequest();
$request->setMchOrderNo(DemoConstants::buildOrderNo())
        ->setWayCode(WayCodeEnum::ALI_QR)            // 支付方式编码
        ->setAmount(DemoConstants::DEMO_AMOUNT_FEN)  // 单位:分
        ->setSubject(DemoConstants::DEMO_SUBJECT)
        ->setBody(DemoConstants::DEMO_BODY)
        ->setNotifyUrl(DemoConstants::DEMO_NOTIFY_URL)
        ->setReturnUrl(DemoConstants::DEMO_RETURN_URL)
        ->setChannelExtra($channelExtra);

// 业务扩展字段（透传给网关，非显式定义的参数）
$request->setExtendInfo([
    // 'remark' => '自定义备注',
]);

// 2. 发起接口调用
try {
    $client = new LsPayClient();              // 默认商户; 多商户用 new LsPayClient('merch_b')
    $result = $client->postRequest($request);

    // 3. 处理结果
    if (!$result || $result->isError()) {
        echo "下单失败: \n";
        var_dump($result ? $result->getErrorInfo() : 'null');
    } else {
        echo "统一下单接口已受理（不代表用户已付款）: \n";
        var_dump($result->getData());
        echo $result->isPaid() ? "订单已支付\n" : "订单尚未支付\n";
    }
} catch (LsPayException $e) {
    echo "SDK 异常: " . $e->getMessage();
}
