<?php
/**
 * 立收通接口端点枚举（功能编码 → 接口路径）
 *
 * 路径来自平台公开接口列表（请求接口地址.md）：
 *   订单查询      https://api.lspay.tech/gateway/query
 *   订单退款      https://api.lspay.tech/gateway/refund
 *   退款订单查询  https://api.lspay.tech/gateway/refund/query
 */
namespace LsPaySdk\enums;

class EndpointEnum
{
    /** 统一下单 */
    const UNIFIED_ORDER = 'gateway/unifiedorder';
    /** 订单查询 */
    const QUERY_ORDER   = 'gateway/query';
    /** 申请退款 */
    const REFUND        = 'gateway/refund';
    /** 退款查询 */
    const QUERY_REFUND  = 'gateway/refund/query';
    /** 延时分账交易确认 */
    const DELAY_TRANS_CONFIRM = 'gateway/delaytrans/confirm';
    /** 交易确认退款 */
    const DELAY_TRANS_CONFIRM_REFUND = 'gateway/delaytrans/confirmrefund';
    /** 可转余额查询 */
    const DELAY_TRANS_UNCONFIRM_QUERY = 'gateway/delaytrans/unconfirm/query';
    /** 延时分账订单转账 */
    const DELAY_TRANS_CREATE_TASK = 'gateway/delaytrans/createtask';
    /** 快捷支付绑卡申请 */
    const QUICK_PAY_BIND_APPLY = 'gateway/quickpay/buckleapply';
    /** 快捷支付绑卡确认 */
    const QUICK_PAY_BIND_CONFIRM = 'gateway/quickpay/buckleconfirm';
    /** 快捷支付确认 */
    const QUICK_PAY_CONFIRM = 'gateway/quickpay/payconfirm';
    /** 快捷支付解绑 */
    const QUICK_PAY_UNBIND = 'gateway/quickpay/payunbind';
    /** 快捷支付短信校验 */
    const QUICK_PAY_SMS_CHECK = 'gateway/quickpay/smscheck';
    /** 电子账户用户开通 */
    const ELECTRONIC_ACCOUNT_USER_OPEN = 'gateway/user/personapply';
    /** 电子账户业务开通 */
    const ELECTRONIC_ACCOUNT_BUSINESS_OPEN = 'gateway/user/personconfirm';
    /** 电子账户业务变更 */
    const ELECTRONIC_ACCOUNT_BUSINESS_CHANGE = 'gateway/user/personmodify';
    /** 电子账户用户查询 */
    const ELECTRONIC_ACCOUNT_USER_QUERY = 'gateway/user/query';
    /** 电子账户结算记录查询 */
    const ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY = 'gateway/user/settlement/query';
    /** 电子账户余额提现 */
    const ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW = 'gateway/user/balance/withdraw';
    /** 电子账户单笔提现查询 */
    const ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY =
        'gateway/user/balance/withdraw/single/query';
    /** 电子账户批量提现查询 */
    const ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY =
        'gateway/user/balance/withdraw/query';
    /** 电子账户余额查询 */
    const BALANCE_QUERY = 'gateway/user/balance/query';

    /** 全部端点映射 */
    public static function all()
    {
        return [
            'unifiedorder' => self::UNIFIED_ORDER,
            'queryorder'   => self::QUERY_ORDER,
            'refund'       => self::REFUND,
            'queryrefund'  => self::QUERY_REFUND,
            'delaytransconfirm' => self::DELAY_TRANS_CONFIRM,
            'delaytransconfirmrefund' => self::DELAY_TRANS_CONFIRM_REFUND,
            'delaytransunconfirmquery' => self::DELAY_TRANS_UNCONFIRM_QUERY,
            'delaytranscreatetask' => self::DELAY_TRANS_CREATE_TASK,
            'quickpaybindapply' => self::QUICK_PAY_BIND_APPLY,
            'quickpaybindconfirm' => self::QUICK_PAY_BIND_CONFIRM,
            'quickpayconfirm' => self::QUICK_PAY_CONFIRM,
            'quickpayunbind' => self::QUICK_PAY_UNBIND,
            'quickpaysmscheck' => self::QUICK_PAY_SMS_CHECK,
            'electronicaccountuseropen' => self::ELECTRONIC_ACCOUNT_USER_OPEN,
            'electronicaccountbusinessopen' => self::ELECTRONIC_ACCOUNT_BUSINESS_OPEN,
            'electronicaccountbusinesschange' => self::ELECTRONIC_ACCOUNT_BUSINESS_CHANGE,
            'electronicaccountuserquery' => self::ELECTRONIC_ACCOUNT_USER_QUERY,
            'electronicaccountsettlementquery' => self::ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY,
            'electronicaccountbalancewithdraw' => self::ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW,
            'electronicaccountsinglewithdrawquery' =>
                self::ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY,
            'electronicaccountbatchwithdrawquery' =>
                self::ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY,
            'balancequery' => self::BALANCE_QUERY,
        ];
    }
}
