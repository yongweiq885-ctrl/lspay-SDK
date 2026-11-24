"""Demo 演示用常量"""

import time

ORDER_PREFIX  = "JT"
REFUND_PREFIX = "JR"

DEFAULT_AMOUNT_FEN = 1
DEFAULT_SUBJECT    = "立收通 SDK Demo 商品"
DEFAULT_BODY       = "立收通 SDK Demo 商品描述"


def gen_order_no() -> str:
    return f"{ORDER_PREFIX}{int(time.time() * 1000)}"


def gen_refund_no() -> str:
    return f"{REFUND_PREFIX}{int(time.time() * 1000)}"
