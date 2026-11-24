"""延时分账确认与转账任务请求构造示例。"""

import loader  # noqa: F401

from lspay import DelayTransConfirmRequest, DelayTransCreateTaskRequest


confirm = DelayTransConfirmRequest(
    mch_order_no="PAY_001",
    order_id="CONFIRM_001",
    channel_extra={
        "div_list": [{"trans_in": "ACCOUNT_IN_001", "div_amt": 100}]
    },
)
transfer = DelayTransCreateTaskRequest(
    account_no="ACCOUNT_001",
    account_name="示例账户",
    order_no="TRANSFER_001",
    amount=100,
)

confirm.validate()
transfer.validate()
print(confirm.get_function_code(), confirm.to_map())
print(transfer.get_function_code(), transfer.to_map())
