<?php
/**
 * 立收通支付订单状态。
 */
namespace LsPaySdk\enums;

final class OrderStateEnum
{
    /** 订单生成 */
    const CREATED = 0;
    /** 支付中 */
    const PAYING = 1;
    /** 支付成功 */
    const PAID = 2;
    /** 支付失败 */
    const FAILED = 3;
    /** 已撤销 */
    const REVOKED = 4;
    /** 已退款 */
    const REFUNDED = 5;
    /** 订单关闭 */
    const CLOSED = 6;

    public static function all()
    {
        return [
            self::CREATED,
            self::PAYING,
            self::PAID,
            self::FAILED,
            self::REVOKED,
            self::REFUNDED,
            self::CLOSED,
        ];
    }

    public static function isValid($value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false
            && in_array((int)$value, self::all(), true);
    }
}
