"""订单查询 Demo"""

import loader
from lspay import LsPayClient, QueryOrderRequest


def main() -> None:
    loader.init()

    req = QueryOrderRequest(mch_order_no="JT1700000000000")
    resp = LsPayClient.execute(req)
    if resp.is_error():
        print("查询失败:", resp.get_error_info())
        return
    print("订单状态:", resp.get_data())


if __name__ == "__main__":
    main()
