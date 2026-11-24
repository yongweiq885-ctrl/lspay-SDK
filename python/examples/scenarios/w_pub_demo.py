"""场景：微信 JSAPI (WX_PUB)

    适用：公众号或微信内 H5 唤起支付，必须通过 channel_extra 传 openid。
    微信扫码请使用 WX_NATIVE。
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
        way_code=WayCode.WX_PUB,
        amount=DEFAULT_AMOUNT_FEN,
        subject=DEFAULT_SUBJECT,
        body=DEFAULT_BODY,
        notify_url=LsPay.get_config().notify_url,
        # 替换为公众号/微信内授权获得的真实 openid。
        channel_extra={"openid": "o-xxxxxxxxxxxxxxxxxxxxxxxx"},
    )
    resp = LsPayClient.execute(req)
    if resp.is_error():
        print("[WX_PUB] 失败:", resp.get_error_info())
        return
    print("[WX_PUB] 微信支付信息:", resp.get_data())


if __name__ == "__main__":
    main()
