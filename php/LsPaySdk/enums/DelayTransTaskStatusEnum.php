<?php
/**
 * 延时分账订单转账任务状态。
 */
namespace LsPaySdk\enums;

final class DelayTransTaskStatusEnum
{
    const PENDING = 'PENDING';
    const PROCESSING = 'PROCESSING';
    const COMPLETED = 'COMPLETED';
    const FAILED = 'FAILED';
    const PARTIAL = 'PARTIAL';

    public static function all()
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::COMPLETED,
            self::FAILED,
            self::PARTIAL,
        ];
    }

    public static function isValid($status)
    {
        return in_array((string)$status, self::all(), true);
    }
}
