"""快捷支付申请与确认请求构造示例。"""

import loader  # noqa: F401

from lspay import QuickPayApplyRequest, QuickPayConfirmRequest


apply_request = QuickPayApplyRequest(
    mch_order_no="QUICK_PAY_001",
    amount=100,
    subject="快捷支付",
    body="快捷支付示例",
    channel_extra={"channel_user": "USER_001", "channel_card_id": "CARD_001"},
)
confirm_request = QuickPayConfirmRequest(
    mch_order_no="QUICK_PAY_001",
    verify_code="123456",
)

apply_request.validate()
confirm_request.validate()
print(apply_request.get_function_code(), apply_request.to_map())
print(confirm_request.get_function_code(), confirm_request.to_map())
