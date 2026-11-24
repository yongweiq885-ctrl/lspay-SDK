<?php
/**
 * 电子账户开户主体类型。
 */
namespace LsPaySdk\enums;

final class ElectronicAccountTypeEnum
{
    const PERSONAL = 1;
    const ENTERPRISE = 2;
    const INDIVIDUAL_BUSINESS = 3;

    public static function all()
    {
        return [self::PERSONAL, self::ENTERPRISE, self::INDIVIDUAL_BUSINESS];
    }

    public static function isValid($value)
    {
        return is_int($value) && in_array($value, self::all(), true);
    }
}
