<?php
/**
 * 异步通知统一入口。
 */
namespace LsPaySdk\notify;

use LsPaySdk\core\LsPay;
use LsPaySdk\exceptions\LsPayConfigException;
use LsPaySdk\exceptions\LsPayValidationException;

final class LsPayNotify
{
    const SUCCESS_RESPONSE = 'ok';
    const FAILURE_RESPONSE = 'fail';

    private function __construct()
    {
    }

    /**
     * 根据 notify_type / event_type 自动识别支付或退款通知。
     */
    public static function parse(array $params, $merchantKey = 'default')
    {
        $notifyType = isset($params['notify_type'])
            ? (string)$params['notify_type']
            : '';
        $eventType = isset($params['event_type'])
            ? (string)$params['event_type']
            : '';

        if ($notifyType === 'refund'
            || strpos($eventType, 'refund.') === 0) {
            return self::parseRefund($params, $merchantKey);
        }
        if (strpos($notifyType, 'payment') === 0
            || strpos($eventType, 'payment.') === 0) {
            return self::parsePayment($params, $merchantKey);
        }
        if (isset(
            $params['task_id'],
            $params['order_no'],
            $params['target_amount'],
            $params['processed_amount'],
            $params['remaining_amount']
        )) {
            return self::parseDelayTransTransfer($params, $merchantKey);
        }
        throw new LsPayValidationException(
            '无法根据 notify_type / event_type 识别通知类型'
        );
    }

    public static function parsePayment(
        array $params,
        $merchantKey = 'default'
    ) {
        return new PaymentNotify(
            $params,
            self::merchantConfig($merchantKey)
        );
    }

    public static function parseRefund(
        array $params,
        $merchantKey = 'default'
    ) {
        return new RefundNotify(
            $params,
            self::merchantConfig($merchantKey)
        );
    }

    public static function parseDelayTransTransfer(
        array $params,
        $merchantKey = 'default'
    ) {
        return new DelayTransTransferNotify(
            $params,
            self::merchantConfig($merchantKey)
        );
    }

    /**
     * 从 PHP multipart/form-data POST 全局变量读取通知。
     */
    public static function fromGlobals($merchantKey = 'default')
    {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper((string)$_SERVER['REQUEST_METHOD'])
            : '';
        if ($method !== 'POST') {
            throw new LsPayValidationException('通知仅允许 HTTP POST');
        }
        $contentType = isset($_SERVER['CONTENT_TYPE'])
            ? (string)$_SERVER['CONTENT_TYPE']
            : '';
        if (stripos($contentType, 'multipart/form-data') !== 0) {
            throw new LsPayValidationException(
                '通知 Content-Type 必须是 multipart/form-data'
            );
        }
        return self::parse($_POST, $merchantKey);
    }

    public static function successResponse()
    {
        return self::SUCCESS_RESPONSE;
    }

    public static function failureResponse()
    {
        return self::FAILURE_RESPONSE;
    }

    private static function merchantConfig($merchantKey)
    {
        $config = LsPay::getConfig($merchantKey);
        if (!$config) {
            throw new LsPayConfigException(
                "商户配置不存在: $merchantKey"
            );
        }
        return $config;
    }
}
