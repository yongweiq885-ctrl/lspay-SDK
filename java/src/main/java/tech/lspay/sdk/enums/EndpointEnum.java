package tech.lspay.sdk.enums;

import java.util.LinkedHashMap;
import java.util.Map;

/**
 * 立收通接口端点枚举
 *
 * 路径来自平台公开接口列表：
 *   订单查询      https://api.lspay.tech/gateway/query
 *   订单退款      https://api.lspay.tech/gateway/refund
 *   退款订单查询  https://api.lspay.tech/gateway/refund/query
 */
public final class EndpointEnum {
    private EndpointEnum() {}

    public static final String UNIFIED_ORDER = "gateway/unifiedorder";
    public static final String QUERY_ORDER   = "gateway/query";
    public static final String REFUND        = "gateway/refund";
    public static final String QUERY_REFUND  = "gateway/refund/query";
    public static final String DELAY_TRANS_CONFIRM = "gateway/delaytrans/confirm";
    public static final String DELAY_TRANS_CONFIRM_REFUND = "gateway/delaytrans/confirmrefund";
    public static final String DELAY_TRANS_UNCONFIRM_QUERY = "gateway/delaytrans/unconfirm/query";
    public static final String DELAY_TRANS_CREATE_TASK = "gateway/delaytrans/createtask";
    public static final String QUICK_PAY_BIND_APPLY = "gateway/quickpay/buckleapply";
    public static final String QUICK_PAY_BIND_CONFIRM = "gateway/quickpay/buckleconfirm";
    public static final String QUICK_PAY_CONFIRM = "gateway/quickpay/payconfirm";
    public static final String QUICK_PAY_UNBIND = "gateway/quickpay/payunbind";
    public static final String QUICK_PAY_SMS_CHECK = "gateway/quickpay/smscheck";
    public static final String ELECTRONIC_ACCOUNT_USER_OPEN = "gateway/user/personapply";
    public static final String ELECTRONIC_ACCOUNT_BUSINESS_OPEN = "gateway/user/personconfirm";
    public static final String ELECTRONIC_ACCOUNT_BUSINESS_CHANGE = "gateway/user/personmodify";
    public static final String ELECTRONIC_ACCOUNT_USER_QUERY = "gateway/user/query";
    public static final String ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY = "gateway/user/settlement/query";
    public static final String ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW = "gateway/user/balance/withdraw";
    public static final String ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY =
            "gateway/user/balance/withdraw/single/query";
    public static final String ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY =
            "gateway/user/balance/withdraw/query";
    public static final String BALANCE_QUERY = "gateway/user/balance/query";

    public static Map<String, String> all() {
        Map<String, String> map = new LinkedHashMap<>();
        map.put("unifiedorder", UNIFIED_ORDER); map.put("queryorder", QUERY_ORDER);
        map.put("refund", REFUND); map.put("queryrefund", QUERY_REFUND);
        map.put("delaytransconfirm", DELAY_TRANS_CONFIRM);
        map.put("delaytransconfirmrefund", DELAY_TRANS_CONFIRM_REFUND);
        map.put("delaytransunconfirmquery", DELAY_TRANS_UNCONFIRM_QUERY);
        map.put("delaytranscreatetask", DELAY_TRANS_CREATE_TASK);
        map.put("quickpaybindapply", QUICK_PAY_BIND_APPLY);
        map.put("quickpaybindconfirm", QUICK_PAY_BIND_CONFIRM);
        map.put("quickpayconfirm", QUICK_PAY_CONFIRM); map.put("quickpayunbind", QUICK_PAY_UNBIND);
        map.put("quickpaysmscheck", QUICK_PAY_SMS_CHECK);
        map.put("electronicaccountuseropen", ELECTRONIC_ACCOUNT_USER_OPEN);
        map.put("electronicaccountbusinessopen", ELECTRONIC_ACCOUNT_BUSINESS_OPEN);
        map.put("electronicaccountbusinesschange", ELECTRONIC_ACCOUNT_BUSINESS_CHANGE);
        map.put("electronicaccountuserquery", ELECTRONIC_ACCOUNT_USER_QUERY);
        map.put("electronicaccountsettlementquery", ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY);
        map.put("electronicaccountbalancewithdraw", ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW);
        map.put("electronicaccountsinglewithdrawquery", ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY);
        map.put("electronicaccountbatchwithdrawquery", ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY);
        map.put("balancequery", BALANCE_QUERY);
        return map;
    }
}
