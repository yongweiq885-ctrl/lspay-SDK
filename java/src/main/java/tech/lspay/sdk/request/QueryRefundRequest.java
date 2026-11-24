package tech.lspay.sdk.request;

import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.response.QueryRefundResponse;

/** 退款订单查询 */
public class QueryRefundRequest extends BaseRequest {

    public String mchRefundNo;
    public String orderRefundId;

    @Override
    public String getFunctionCode() { return EndpointEnum.QUERY_REFUND; }
    @Override public Class<QueryRefundResponse> getResponseClass() { return QueryRefundResponse.class; }

    public QueryRefundRequest setMchRefundNo(String v)   { this.mchRefundNo = v;   return this; }
    public QueryRefundRequest setOrderRefundId(String v) { this.orderRefundId = v; return this; }
    /** @deprecated 使用 setOrderRefundId */
    @Deprecated public QueryRefundRequest setRefundOrderId(String v) { return setOrderRefundId(v); }

    @Override public void validate() {
        optional("mch_refund_no", mchRefundNo, 30);
        optional("order_refund_id", orderRefundId, 30);
        if ((mchRefundNo == null || mchRefundNo.trim().isEmpty())
                && (orderRefundId == null || orderRefundId.trim().isEmpty()))
            throw new IllegalArgumentException("mch_refund_no 与 order_refund_id 至少填写一个");
    }
}
