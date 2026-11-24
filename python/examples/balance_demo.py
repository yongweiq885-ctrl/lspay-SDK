"""余额支付与余额查询请求构造示例。"""

import loader  # noqa: F401

from lspay import BalancePayRequest, BalanceQueryRequest


pay = BalancePayRequest(
    mch_order_no="BALANCE_PAY_001",
    amount=100,
    subject="余额支付",
    body="余额支付示例",
    channel_extra={
        "trans_out": "350873420280024268",
        "div_list": [{"trans_in": "3709180455068368896", "div_amt": 100}],
    },
)
query = BalanceQueryRequest(order_id="BALANCE_QUERY_001", account_no="ACCOUNT_001")

pay.validate()
query.validate()
print(pay.get_function_code(), pay.to_map())
print(query.get_function_code(), query.to_map())
