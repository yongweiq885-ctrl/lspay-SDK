package tech.lspay.sdk.request;

import com.alibaba.fastjson2.JSON;
import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.enums.WayCodeEnum;

import java.lang.reflect.Field;
import java.util.LinkedHashMap;
import java.util.Map;

/** Map 兼容路由：已知端点进入与强类型调用相同的校验路径。 */
public final class RequestFactory {
    private RequestFactory() {}

    public static BaseRequest fromMap(String endpoint, Map<String, ?> params) {
        Map<String, ?> values = params == null ? new LinkedHashMap<String, Object>() : params;
        BaseRequest request = create(endpoint, values);
        Map<String, Object> unknown = new LinkedHashMap<>();
        for (Map.Entry<String, ?> entry : values.entrySet()) {
            String fieldName = camel(entry.getKey());
            Field field = findField(request.getClass(), fieldName);
            if (field == null || "extendInfos".equals(fieldName)) {
                unknown.put(entry.getKey(), entry.getValue());
                continue;
            }
            try {
                Object value = entry.getValue();
                if (value != null && ("channelExtra".equals(fieldName) || "extParam".equals(fieldName))
                        && !(value instanceof String)) value = JSON.toJSONString(value);
                if (value instanceof Number && field.getType() == Long.class) value = ((Number) value).longValue();
                if (value instanceof Number && field.getType() == Integer.class) value = ((Number) value).intValue();
                if (value != null && field.getType() == String.class && !(value instanceof String))
                    value = String.valueOf(value);
                field.set(request, value);
            } catch (ReflectiveOperationException | IllegalArgumentException e) {
                throw new IllegalArgumentException("参数 " + entry.getKey() + " 类型不兼容", e);
            }
        }
        request.setExtendInfo(unknown);
        return request;
    }

    public static boolean supports(String endpoint) {
        try { create(endpoint, new LinkedHashMap<String, Object>()); return true; }
        catch (IllegalArgumentException e) { return false; }
    }

    private static BaseRequest create(String endpoint, Map<String, ?> params) {
        if (EndpointEnum.UNIFIED_ORDER.equals(endpoint)) {
            Object way = params.get("way_code");
            if (WayCodeEnum.BANK_FAST.equals(String.valueOf(way))) return new QuickPayApplyRequest();
            if (WayCodeEnum.BALANCE_PAY.equals(String.valueOf(way))) return new BalancePayRequest();
            return new UnifiedOrderRequest();
        }
        if (EndpointEnum.QUERY_ORDER.equals(endpoint)) return new QueryOrderRequest();
        if (EndpointEnum.REFUND.equals(endpoint)) return new RefundRequest();
        if (EndpointEnum.QUERY_REFUND.equals(endpoint)) return new QueryRefundRequest();
        if (EndpointEnum.DELAY_TRANS_CONFIRM.equals(endpoint)) return new DelayTransConfirmRequest();
        if (EndpointEnum.DELAY_TRANS_CONFIRM_REFUND.equals(endpoint)) return new DelayTransConfirmRefundRequest();
        if (EndpointEnum.DELAY_TRANS_UNCONFIRM_QUERY.equals(endpoint)) return new DelayTransUnconfirmQueryRequest();
        if (EndpointEnum.DELAY_TRANS_CREATE_TASK.equals(endpoint)) return new DelayTransCreateTaskRequest();
        if (EndpointEnum.QUICK_PAY_BIND_APPLY.equals(endpoint)) return new QuickPayBindApplyRequest();
        if (EndpointEnum.QUICK_PAY_BIND_CONFIRM.equals(endpoint)) return new QuickPayBindConfirmRequest();
        if (EndpointEnum.QUICK_PAY_CONFIRM.equals(endpoint)) return new QuickPayConfirmRequest();
        if (EndpointEnum.QUICK_PAY_UNBIND.equals(endpoint)) return new QuickPayUnbindRequest();
        if (EndpointEnum.QUICK_PAY_SMS_CHECK.equals(endpoint)) return new QuickPaySmsCheckRequest();
        if (EndpointEnum.BALANCE_QUERY.equals(endpoint)) return new BalanceQueryRequest();
        if (EndpointEnum.ELECTRONIC_ACCOUNT_USER_OPEN.equals(endpoint)) return new ElectronicAccountUserOpenRequest();
        if (EndpointEnum.ELECTRONIC_ACCOUNT_BUSINESS_OPEN.equals(endpoint)) return new ElectronicAccountBusinessOpenRequest();
        if (EndpointEnum.ELECTRONIC_ACCOUNT_BUSINESS_CHANGE.equals(endpoint)) return new ElectronicAccountBusinessChangeRequest();
        if (EndpointEnum.ELECTRONIC_ACCOUNT_USER_QUERY.equals(endpoint)) return new ElectronicAccountUserQueryRequest();
        if (EndpointEnum.ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY.equals(endpoint)) return new ElectronicAccountSettlementQueryRequest();
        if (EndpointEnum.ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW.equals(endpoint)) return new ElectronicAccountBalanceWithdrawRequest();
        if (EndpointEnum.ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY.equals(endpoint)) return new ElectronicAccountSingleWithdrawQueryRequest();
        if (EndpointEnum.ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY.equals(endpoint)) return new ElectronicAccountBatchWithdrawQueryRequest();
        throw new IllegalArgumentException("不支持的端点: " + endpoint);
    }

    private static String camel(String value) {
        StringBuilder result = new StringBuilder();
        boolean upper = false;
        for (char c : value.toCharArray()) {
            if (c == '_') upper = true;
            else { result.append(upper ? Character.toUpperCase(c) : c); upper = false; }
        }
        return result.toString();
    }

    private static Field findField(Class<?> type, String name) {
        for (Class<?> c = type; c != null && c != Object.class; c = c.getSuperclass()) {
            try { return c.getField(name); } catch (NoSuchFieldException ignored) {}
        }
        return null;
    }
}
