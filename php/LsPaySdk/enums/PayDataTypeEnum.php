<?php
/**
 * channel_extra.pay_data_type 可选值。
 *
 * 该参数决定统一下单响应中 pay_info 的数据类型；不传时平台默认 pay_url。
 */
namespace LsPaySdk\enums;

final class PayDataTypeEnum
{
    /** 立收通统一收银台（form 表单跳转） */
    const CASHIER      = 'cashier';
    /** 支付链接（平台默认） */
    const PAY_URL      = 'pay_url';
    /** 微信原生支付参数 */
    const WXAPP        = 'wxapp';
    /** 支付宝 APP 支付参数 */
    const ALIAPP       = 'aliapp';
    /** 云闪付 APP 支付参数 */
    const YSFAPP       = 'ysfapp';
    /** 二维码内容地址 */
    const CODE_URL     = 'code_url';
    /** 二维码图片地址 */
    const CODE_IMG_URL = 'code_img_url';
    /** 不返回支付参数 */
    const NONE         = 'none';

    public static function all()
    {
        return [
            self::CASHIER,
            self::PAY_URL,
            self::WXAPP,
            self::ALIAPP,
            self::YSFAPP,
            self::CODE_URL,
            self::CODE_IMG_URL,
            self::NONE,
        ];
    }

    public static function isValid($value)
    {
        return in_array((string)$value, self::all(), true);
    }
}
