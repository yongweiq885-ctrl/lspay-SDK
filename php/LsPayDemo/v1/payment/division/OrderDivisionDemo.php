<?php
/**
 * 订单分账示例。
 *
 * 订单分账仍调用统一下单端点 /gateway/unifiedorder：
 *   division_mode=1 实时分账
 *   division_mode=2 延时分账
 * 分账接收方放入 channel_extra.div_list。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\PayDataTypeEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\DivisionReceiver;
use LsPaySdk\request\UnifiedOrderChannelExtra;
use LsPaySdk\request\UnifiedOrderRequest;

$channelExtra = (new UnifiedOrderChannelExtra())
    ->addDivisionReceiver(
        (new DivisionReceiver('YOUR_RECEIVER_USER_ID'))
            ->setDivAmt(10)
            ->setDescription('经销商订单分账')
    )
    ->addDivisionReceiver(
        (new DivisionReceiver('YOUR_SECOND_RECEIVER_USER_ID'))
            ->setDescription('按平台配置比例分账')
    )
    ->setPayDataType(PayDataTypeEnum::CODE_IMG_URL);

$request = (new UnifiedOrderRequest())
    ->setMchOrderNo('DIV' . date('YmdHis') . mt_rand(100000, 999999))
    ->setWayCode(WayCodeEnum::ALI_QR)
    ->setAmount(100)
    ->setSubject('分账测试订单')
    ->setBody('立收通订单分账示例')
    ->setNotifyUrl('https://your.domain/notify.php')
    ->setDivisionMode(1)
    ->setChannelExtra($channelExtra);

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "分账订单创建失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }
    echo "分账订单已受理（不代表用户已付款或分账已完成）:\n";
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
