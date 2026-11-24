<?php
/**
 * 可转余额查询示例。
 *
 * 本接口没有业务请求参数，公共参数由 SDK 自动注入。
 */
namespace LsPayDemo;

define('BASE_PATH', realpath(__DIR__ . '/../../../..'));
require_once BASE_PATH . '/LsPayDemo/loader.php';

use LsPaySdk\core\LsPayClient;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\DelayTransUnconfirmQueryRequest;

$request = new DelayTransUnconfirmQueryRequest();

try {
    $result = (new LsPayClient())->postRequest($request);
    if (!$result || $result->isError()) {
        echo "可转余额查询失败:\n";
        var_dump($result ? $result->getErrorInfo() : 'null');
        exit;
    }

    echo '当前可转余额（分）: ' . $result->getUnconfirmAmount() . "\n";
} catch (LsPayException $e) {
    echo 'SDK 异常: ' . $e->getMessage() . "\n";
}
