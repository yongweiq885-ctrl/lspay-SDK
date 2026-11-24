"""退款订单查询 Demo"""

import loader
from lspay import LsPayClient, QueryRefundRequest


def main() -> None:
    loader.init()

    req = QueryRefundRequest(mch_refund_no="JR1700000000000")
    resp = LsPayClient.execute(req)
    if resp.is_error():
        print("退款查询失败:", resp.get_error_info())
        return
    print("退款状态:", resp.get_data())


if __name__ == "__main__":
    main()
