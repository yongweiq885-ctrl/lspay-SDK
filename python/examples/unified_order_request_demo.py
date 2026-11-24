"""统一下单 Demo（支付宝当面付）"""

import loader
from demo_constants import (
    DEFAULT_AMOUNT_FEN,
    DEFAULT_BODY,
    DEFAULT_SUBJECT,
    gen_order_no,
)
from lspay import LsPay, LsPayClient, UnifiedOrderRequest, WayCode


def main() -> None:
    loader.init()

    req = UnifiedOrderRequest(
        mch_order_no=gen_order_no(),
        way_code=WayCode.ALI_QR,
        amount=DEFAULT_AMOUNT_FEN,
        subject=DEFAULT_SUBJECT,
        body=DEFAULT_BODY,
        notify_url=LsPay.get_config("default").notify_url,
        client_ip="127.0.0.1",
        channel_extra={"pay_data_type": "pay_url"},
    )

    resp = LsPayClient.execute(req)
    if resp.is_error():
        print("下单失败:", resp.get_error_info())
        return
    print("下单成功:", resp.get_data())


if __name__ == "__main__":
    main()
