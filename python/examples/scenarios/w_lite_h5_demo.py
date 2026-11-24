"""微信 App / H5 跳转立收通收银台 (WX_LITE_H5)。

直连 App 请使用 WX_APP。
"""

import json
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
        way_code=WayCode.WX_LITE_H5,
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
        print("[WX_LITE_H5] 失败:", resp.get_error_info())
        return
    pay_info = resp.get_pay_info()
    if isinstance(pay_info, str):
        pay_info = json.loads(pay_info)
    if isinstance(pay_info, dict) and pay_info.get("path"):
        pay_info["path"] = (
            pay_info["path"] + "?p=" + str(resp.get_pay_order_id()) + "&s=app"
        )
    print("[WX_LITE_H5] App 使用 gh_id 和 path；H5 打开 scheme_code:", pay_info)


if __name__ == "__main__":
    main()
