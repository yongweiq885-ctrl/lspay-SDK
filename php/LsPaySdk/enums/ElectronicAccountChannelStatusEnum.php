<?php
/**
 * 电子账户入驻状态。
 */
namespace LsPaySdk\enums;

final class ElectronicAccountChannelStatusEnum
{
    const PENDING = '0';
    const SUCCEEDED = '1';
    const FAILED = '2';

    public static function all()
    {
        return [self::PENDING, self::SUCCEEDED, self::FAILED];
    }
}
