"""支付通知验签、业务断言与幂等键 Demo（本地构造，不发网络请求）。"""

import loader
from lspay import LsPay, LsPayNotify, LsPayTools


def main() -> None:
    loader.init()

    config = LsPay.get_config("default")
    data = {
        "notify_type": "payment.succeeded",
        "event_type": "payment.succeeded",
        "pay_order_id": "8835508652407259136",
        "mch_no": config.mch_no,
        "app_id": config.app_id,
        "mch_order_no": "JT123",
        "way_code": "WX_PUB",
        "amount": "1",
        "currency": "cny",
        "state": "2",
        "subject": "测试商品",
        "body": "通知演示",
        "add_time": "1790487185",
        "success_time": "1790487200",
        "req_time": "1790487185050",
    }
    data["sign"] = LsPayTools.sign(data, config.md5_key)
    notify = LsPayNotify.parse(data)
    notify.assert_order("JT123", 1, "cny")
    print("验签通过:", notify.get_idempotency_key())
    print("Reply:", LsPayNotify.success_response())


if __name__ == "__main__":
    main()
