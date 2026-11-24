<?php
/**
 * 立收通退款订单状态。
 */
namespace LsPaySdk\enums;

final class RefundStateEnum
{
    /** 退款订单生成 */
    const CREATED = 0;
    /** 退款处理中 */
    const REFUNDING = 1;
    /** 退款成功 */
    const SUCCEEDED = 2;
    /** 退款失败 */
    const FAILED = 3;
    /** 退款关闭 */
    const CLOSED = 4;

    public static function all()
    {
        return [
            self::CREATED,
            self::REFUNDING,
            self::SUCCEEDED,
            self::FAILED,
            self::CLOSED,
        ];
    }

    public static function isValid($value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false
            && in_array((int)$value, self::all(), true);
    }
}
