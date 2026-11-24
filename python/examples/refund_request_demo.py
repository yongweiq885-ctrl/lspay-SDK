"""申请退款 Demo"""

import loader
from demo_constants import gen_refund_no
from lspay import LsPayClient, RefundRequest


def main() -> None:
    loader.init()

    req = RefundRequest(
        mch_refund_no=gen_refund_no(),
        mch_order_no="JT1700000000000",
        refund_amount=1,
        refund_reason="用户申请退款",
    )
    resp = LsPayClient.execute(req)
    if resp.is_error():
        print("退款失败:", resp.get_error_info())
        return
    print("退款受理:", resp.get_data())


if __name__ == "__main__":
    main()
