<?php
/**
 * 电子账户提现交易状态。
 */
namespace LsPaySdk\enums;

final class ElectronicAccountWithdrawStatusEnum
{
    const PENDING = 'pending';
    const FAILED = 'failed';
    const SUCCEEDED = 'succeeded';

    public static function all()
    {
        return [self::PENDING, self::FAILED, self::SUCCEEDED];
    }
}
