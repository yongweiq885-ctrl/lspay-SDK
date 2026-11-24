package tech.lspay.sdk.request;

import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.response.QueryOrderResponse;

/** 订单查询（mchOrderNo / payOrderId 二选一） */
public class QueryOrderRequest extends BaseRequest {

    public String mchOrderNo;
    public String payOrderId;

    @Override
    public String getFunctionCode() { return EndpointEnum.QUERY_ORDER; }
    @Override public Class<QueryOrderResponse> getResponseClass() { return QueryOrderResponse.class; }

    public QueryOrderRequest setMchOrderNo(String v) { this.mchOrderNo = v; return this; }
    public QueryOrderRequest setPayOrderId(String v) { this.payOrderId = v; return this; }

    @Override public void validate() {
        optional("mch_order_no", mchOrderNo, 30);
        optional("pay_order_id", payOrderId, 30);
        if ((mchOrderNo == null || mchOrderNo.trim().isEmpty())
                && (payOrderId == null || payOrderId.trim().isEmpty()))
            throw new IllegalArgumentException("mch_order_no 与 pay_order_id 至少填写一个");
    }
}
