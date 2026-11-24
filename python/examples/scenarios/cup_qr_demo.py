"""场景：云闪付扫码 (CUP_QR)"""

import os
import sys

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

import loader  # noqa: E402
from demo_constants import (  # noqa: E402
    DEFAULT_AMOUNT_FEN,
    DEFAULT_BODY,
    DEFAULT_SUBJECT,
    gen_order_no,
)
from lspay import LsPay, LsPayClient, UnifiedOrderRequest, WayCode  # noqa: E402


def main() -> None:
    loader.init()

    req = UnifiedOrderRequest(
        mch_order_no=gen_order_no(),
        way_code=WayCode.CUP_QR,
        amount=DEFAULT_AMOUNT_FEN,
        subject=DEFAULT_SUBJECT,
        body=DEFAULT_BODY,
        notify_url=LsPay.get_config().notify_url,
        channel_extra={"pay_data_type": "code_url"},
    )
    resp = LsPayClient.execute(req)
    if resp.is_error():
        print("[CUP_QR] 失败:", resp.get_error_info())
        return
    print("[CUP_QR] 云闪付二维码:", resp.get_data())


if __name__ == "__main__":
    main()
