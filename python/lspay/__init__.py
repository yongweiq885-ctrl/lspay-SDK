"""
立收通 (LSPay) Python SDK

最简用法：

    from lspay import LsPay, LsPayClient, MerConfig, WayCode, UnifiedOrderRequest

    LsPay.init("default", MerConfig(
        mch_no="...", app_id="...", md5_key="...",
        notify_url="https://your.domain/notify",
    ))

    req = UnifiedOrderRequest(
        mch_order_no="JT1700000000000",
        way_code=WayCode.ALI_QR,
        amount=1,
        subject="测试",
        body="测试商品描述",
        channel_extra={"pay_data_type": "code_url"},
    )
    resp = LsPayClient.execute(req)
    if resp.is_error():
        print(resp.get_error_info())
    else:
        print(resp.get_data())
"""

from .config import MerConfig                      # noqa: F401
from .core.cashier_form import CashierForm          # noqa: F401
from .core.ls_pay import LsPay                      # noqa: F401
from .core.ls_pay_client import LsPayClient         # noqa: F401
from .core.ls_pay_response import LsPayResponse     # noqa: F401
from .core.ls_pay_tools import LsPayTools           # noqa: F401
from .enums.way_code import WayCode                 # noqa: F401
from .enums.endpoint import Endpoint                # noqa: F401
from .exceptions import (                            # noqa: F401
    LsPayException,
    LsPayConfigException,
    LsPayNetworkException,
    LsPaySignException,
    LsPayBizException,
    LsPayValidationException,
    LsPayProtocolException,
)
from .notify import *                                                   # noqa: F401,F403
from .request import *                                                  # noqa: F401,F403
from .response import *                                                 # noqa: F401,F403

__version__ = "3.0.0"
