"""微信小程序原生支付 (WX_LITE)。

channel_extra 只序列化一次：{"openid":"..."}。
响应 pay_info 是 JSON 字符串，解析后把字段交给 wx.requestPayment。signType 用返回值。
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
        way_code=WayCode.WX_LITE,
        amount=DEFAULT_AMOUNT_FEN,
        subject=DEFAULT_SUBJECT,
        body=DEFAULT_BODY,
        notify_url=LsPay.get_config().notify_url,
        channel_extra={"openid": "oUpF8uMuAJO_M2pxb1Q9zNjWeS6o"},
    )
    resp = LsPayClient.execute(req)
    if resp.is_error():
        print("[WX_LITE] 失败:", resp.get_error_info())
        return
    pay_info = resp.get_pay_info()
    if isinstance(pay_info, str):
        pay_info = json.loads(pay_info)
    print("[WX_LITE] 调起参数:", pay_info)


if __name__ == "__main__":
    main()
