<?php
/**
 * 电子账户余额账户状态。
 */
namespace LsPaySdk\enums;

final class ElectronicAccountBalanceStatusEnum
{
    const NORMAL = 'N';
    const CLOSED = 'C';
    const FROZEN = 'F';
    const CANCELLED = 'D';

    public static function all()
    {
        return [self::NORMAL, self::CLOSED, self::FROZEN, self::CANCELLED];
    }
}
