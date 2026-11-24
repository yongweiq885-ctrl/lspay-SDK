<?php
/**
 * 电子账户与商户关系类型。
 */
namespace LsPaySdk\enums;

final class ElectronicAccountRelationTypeEnum
{
    const SERVICE_PROVIDER = 'SERVICE_PROVIDER';
    const STORE = 'STORE';
    const STAFF = 'STAFF';
    const STORE_OWNER = 'STORE_OWNER';
    const PARTNER = 'PARTNER';
    const HEADQUARTER = 'HEADQUARTER';
    const BRAND = 'BRAND';
    const DISTRIBUTOR = 'DISTRIBUTOR';
    const USER = 'USER';
    const SUPPLIER = 'SUPPLIER';
    const CUSTOM = 'CUSTOM';

    public static function all()
    {
        return [
            self::SERVICE_PROVIDER,
            self::STORE,
            self::STAFF,
            self::STORE_OWNER,
            self::PARTNER,
            self::HEADQUARTER,
            self::BRAND,
            self::DISTRIBUTOR,
            self::USER,
            self::SUPPLIER,
            self::CUSTOM,
        ];
    }

    public static function isValid($value)
    {
        return in_array((string)$value, self::all(), true);
    }
}
