<?php
/**
 * 快捷支付交易状态。
 */
namespace LsPaySdk\enums;

final class QuickPayTransStatusEnum
{
    /** 失败 */
    const FAILED = 'F';
    /** 处理中 */
    const PROCESSING = 'P';
    /** 成功 */
    const SUCCEEDED = 'S';

    public static function all()
    {
        return [self::FAILED, self::PROCESSING, self::SUCCEEDED];
    }

    public static function isValid($status)
    {
        return in_array((string)$status, self::all(), true);
    }
}
