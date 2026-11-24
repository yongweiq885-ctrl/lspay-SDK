<?php
/**
 * 电子账户交易状态。
 */
namespace LsPaySdk\enums;

final class ElectronicAccountTransStatusEnum
{
    const FAILED = 'F';
    const PROCESSING = 'P';
    const SUCCEEDED = 'S';

    public static function all()
    {
        return [self::FAILED, self::PROCESSING, self::SUCCEEDED];
    }
}
