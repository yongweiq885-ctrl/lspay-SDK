<?php
/**
 * Demo 演示参数集中存放
 * 这些值只用于 Demo 自测，不会进入正式业务代码
 */
namespace LsPayDemo\constants;

class DemoConstants
{
    /** 演示用商户订单号前缀 */
    const ORDER_NO_PREFIX  = 'E';

    /** 演示用退款单号前缀 */
    const REFUND_NO_PREFIX = 'R';

    /** 演示用支付金额（分）—— 1 分 = 0.01 元 */
    const DEMO_AMOUNT_FEN  = 1;

    /** 演示用商品标题 */
    const DEMO_SUBJECT     = '测试订单';

    /** 演示用商品描述 */
    const DEMO_BODY        = '商品描述';

    /** 异步通知 demo 地址（如已在 config.json 中配置 notify_url，会自动覆盖此处） */
    const DEMO_NOTIFY_URL  = 'https://your.domain/notify.php';

    /** 同步回跳 demo 地址 */
    const DEMO_RETURN_URL  = 'https://your.domain/return.php';

    /** 生成 demo 商户订单号 */
    public static function buildOrderNo()
    {
        return self::ORDER_NO_PREFIX . date('YmdHis') . mt_rand(100000, 999999);
    }

    /** 生成 demo 退款单号 */
    public static function buildRefundNo()
    {
        return self::REFUND_NO_PREFIX . date('YmdHis') . mt_rand(100000, 999999);
    }
}
