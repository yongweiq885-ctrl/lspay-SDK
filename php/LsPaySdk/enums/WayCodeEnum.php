<?php
/**
 * 立收通统一网关 way_code。
 *
 * way_code 不仅包含支付方式，也包含用户入驻、余额、结算和提现等业务能力。
 */
namespace LsPaySdk\enums;

class WayCodeEnum
{
    // ============ 支付宝 ============
    /** 支付宝条码 */
    const ALI_BAR     = 'ALI_BAR';
    /** 支付宝 JSAPI（间连，线上扫码推荐） */
    const ALI_PUB     = 'ALI_PUB';
    /** 支付宝 APP（直连） */
    const ALI_APP     = 'ALI_APP';
    /** 支付宝 H5（直连） */
    const ALI_H5      = 'ALI_H5';
    /** 支付宝外或 App 唤起收银台 */
    const ALI_LITE_H5 = 'ALI_LITE_H5';
    /** 支付宝电脑网站（直连） */
    const ALI_PC      = 'ALI_PC';
    /** 支付宝扫码（间连，可在支付宝外拉起收银台） */
    const ALI_QR      = 'ALI_QR';
    /** 支付宝小程序（直连） */
    const ALI_LITE    = 'ALI_LITE';

    // ============ 微信 ============
    /** 微信条码 */
    const WX_BAR       = 'WX_BAR';
    /** 微信 JSAPI（间连，扫码或公众号） */
    const WX_PUB       = 'WX_PUB';
    /** 微信小程序 */
    const WX_LITE      = 'WX_LITE';
    /** 微信 APP（直连） */
    const WX_APP       = 'WX_APP';
    /** 微信外或 App 唤起收银台（建议安卓） */
    const WX_LITE_H5   = 'WX_LITE_H5';
    /** 小程序跳立收通收银台 */
    const WX_LITE_JUMP = 'WX_LITE_JUMP';
    /** 微信扫码（直连） */
    const WX_NATIVE    = 'WX_NATIVE';

    // ============ 云闪付 / 银联 ============
    /** 云闪付条码 */
    const YSF_BAR   = 'YSF_BAR';
    /** 云闪付线上（小程序或扫码） */
    const YSF_JSAPI = 'YSF_JSAPI';
    /** 云闪付扫码 */
    const CUP_QR    = 'CUP_QR';

    // ============ 快捷支付 ============
    /** 快捷支付 */
    const BANK_FAST      = 'BANK_FAST';
    /** 快捷支付收银台 */
    const BANK_FAST_JUMP = 'BANK_FAST_JUMP';

    // ============ 平台业务 ============
    /** 用户入驻 */
    const APPLY            = 'APPLY';
    /** 余额支付 */
    const BALANCE_PAY      = 'BALANCE_PAY';
    /** 余额查询 */
    const BALANCE_QUERY    = 'BALANCE_QUERY';
    /** 结算查询 */
    const SETTLE_QUERY     = 'SETTLE_QUERY';
    /** 余额提现 */
    const BALANCE_WITHDRAW = 'BALANCE_WITHDRAW';
    /** 单笔提现查询 */
    const WITHDRAW_SINGLE_QUERY = 'WITHDRAW_SINGLE_QUERY';
    /** 批量提现查询 */
    const WITHDRAW_QUERY = 'WITHDRAW_QUERY';

    /**
     * 获取所有支持的 way_code 与中文描述
     */
    public static function all()
    {
        return [
            self::ALI_BAR     => '支付宝条码',
            self::ALI_PUB     => '支付宝 JSAPI（间连，线上扫码推荐）',
            self::ALI_APP     => '支付宝 APP（直连）',
            self::ALI_H5      => '支付宝 H5（直连）',
            self::ALI_LITE_H5 => '支付宝外或 App 唤起收银台',
            self::ALI_PC      => '支付宝电脑网站（直连）',
            self::ALI_QR      => '支付宝扫码（间连，可在支付宝外拉起收银台）',
            self::ALI_LITE    => '支付宝小程序（直连）',
            self::WX_BAR       => '微信条码',
            self::WX_PUB       => '微信 JSAPI（间连，扫码或公众号）',
            self::WX_LITE      => '微信小程序',
            self::WX_APP       => '微信 APP（直连）',
            self::WX_LITE_H5   => '微信外或 App 唤起收银台（建议安卓）',
            self::WX_LITE_JUMP => '小程序跳立收通收银台',
            self::WX_NATIVE    => '微信扫码（直连）',
            self::YSF_BAR   => '云闪付条码',
            self::YSF_JSAPI => '云闪付线上（小程序或扫码）',
            self::CUP_QR    => '云闪付扫码',
            self::BANK_FAST      => '快捷支付',
            self::BANK_FAST_JUMP => '快捷支付收银台',
            self::APPLY            => '用户入驻',
            self::BALANCE_PAY      => '余额支付',
            self::BALANCE_QUERY    => '余额查询',
            self::SETTLE_QUERY     => '结算查询',
            self::BALANCE_WITHDRAW => '余额提现',
            self::WITHDRAW_SINGLE_QUERY => '单笔提现查询',
            self::WITHDRAW_QUERY => '批量提现查询',
        ];
    }

    /** 统一下单可选择的支付方式，不包含余额、结算、提现等业务代码。 */
    public static function paymentMethods()
    {
        $codes = [
            self::ALI_BAR,
            self::ALI_PUB,
            self::ALI_APP,
            self::ALI_H5,
            self::ALI_LITE_H5,
            self::ALI_PC,
            self::ALI_QR,
            self::ALI_LITE,
            self::WX_BAR,
            self::WX_PUB,
            self::WX_LITE,
            self::WX_APP,
            self::WX_LITE_H5,
            self::WX_LITE_JUMP,
            self::WX_NATIVE,
            self::YSF_BAR,
            self::YSF_JSAPI,
            self::CUP_QR,
            self::BANK_FAST,
            self::BANK_FAST_JUMP,
        ];
        return array_intersect_key(self::all(), array_flip($codes));
    }

    /** 判断 way_code 是否在平台清单中。 */
    public static function isValid($wayCode)
    {
        return array_key_exists((string)$wayCode, self::all());
    }
}
