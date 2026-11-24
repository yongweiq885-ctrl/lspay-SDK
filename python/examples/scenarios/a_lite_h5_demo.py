"""支付宝 App / H5 跳转收银台 (ALI_LITE_H5)。

App 只用 schema 打开 jump_url。下单入参 app_schema 要带 ://，原生 scheme 不要带。
"""

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
        way_code=WayCode.ALI_LITE_H5,
        amount=DEFAULT_AMOUNT_FEN,
        subject=DEFAULT_SUBJECT,
        body=DEFAULT_BODY,
        client_ip="114.114.114.114",
        notify_url=LsPay.get_config().notify_url,
        return_url="https://your.domain/pay_result.html",
        channel_extra={"pay_data_type": "pay_url"},
    )
    resp = LsPayClient.execute(req)
    if resp.is_error():
        print("[ALI_LITE_H5] 失败:", resp.get_error_info())
        return
    print("[ALI_LITE_H5] App 打开 jump_url:", resp.get_data())


if __name__ == "__main__":
    main()
