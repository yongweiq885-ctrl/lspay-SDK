<?php
/**
 * 延时分账订单转账示例。
 *
 * 转账任务创建成功后，最终结果以验签后的异步通知为准。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\DelayTransCreateTaskRequest;

$request = (new DelayTransCreateTaskRequest())
    ->setAccountNo('YOUR_ELECTRONIC_ACCOUNT')
    ->setAccountName('分销商')
    ->setOrderNo('TRANSFER' . date('YmdHis') . mt_rand(1000, 9999))
    ->setAmount(100)
    ->setNotifyUrl('https://your.domain/delaytrans-notify.php')
    ->setRemark('延时分账订单转账');

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "转账任务创建失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo $result->isCreated()
        ? "转账任务已创建，等待异步结果:\n"
        : "接口已响应，但任务状态不是 PENDING:\n";
    var_dump($result->getData());
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
