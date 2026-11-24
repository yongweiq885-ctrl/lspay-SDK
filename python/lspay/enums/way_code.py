"""
立收通统一网关 way_code。

way_code 不仅包含支付方式，也包含用户入驻、余额、结算和提现等业务能力。
"""


class WayCode:
    # 支付宝
    ALI_BAR     = "ALI_BAR"
    ALI_PUB     = "ALI_PUB"
    ALI_APP     = "ALI_APP"
    ALI_H5      = "ALI_H5"
    ALI_LITE_H5 = "ALI_LITE_H5"
    ALI_PC      = "ALI_PC"
    ALI_QR      = "ALI_QR"
    ALI_LITE    = "ALI_LITE"

    # 微信
    WX_BAR       = "WX_BAR"
    WX_PUB       = "WX_PUB"
    WX_LITE      = "WX_LITE"
    WX_APP       = "WX_APP"
    WX_LITE_H5   = "WX_LITE_H5"
    WX_LITE_JUMP = "WX_LITE_JUMP"
    WX_NATIVE    = "WX_NATIVE"

    # 云闪付 / 银联
    YSF_BAR   = "YSF_BAR"
    YSF_JSAPI = "YSF_JSAPI"
    CUP_QR    = "CUP_QR"

    # 快捷支付
    BANK_FAST      = "BANK_FAST"
    BANK_FAST_JUMP = "BANK_FAST_JUMP"

    # 平台业务
    APPLY            = "APPLY"
    BALANCE_PAY      = "BALANCE_PAY"
    BALANCE_QUERY    = "BALANCE_QUERY"
    SETTLE_QUERY     = "SETTLE_QUERY"
    BALANCE_WITHDRAW = "BALANCE_WITHDRAW"
    WITHDRAW_SINGLE_QUERY = "WITHDRAW_SINGLE_QUERY"
    WITHDRAW_QUERY = "WITHDRAW_QUERY"

    @classmethod
    def all(cls):
        return {
            cls.ALI_BAR:     "支付宝条码",
            cls.ALI_PUB:     "支付宝 JSAPI（间连，线上扫码推荐）",
            cls.ALI_APP:     "支付宝 APP（直连）",
            cls.ALI_H5:      "支付宝 H5（直连）",
            cls.ALI_LITE_H5: "支付宝外或 App 唤起收银台",
            cls.ALI_PC:      "支付宝电脑网站（直连）",
            cls.ALI_QR:      "支付宝扫码（间连，可在支付宝外拉起收银台）",
            cls.ALI_LITE:    "支付宝小程序（直连）",
            cls.WX_BAR:       "微信条码",
            cls.WX_PUB:       "微信 JSAPI（间连，扫码或公众号）",
            cls.WX_LITE:      "微信小程序",
            cls.WX_APP:       "微信 APP（直连）",
            cls.WX_LITE_H5:   "微信外或 App 唤起收银台（建议安卓）",
            cls.WX_LITE_JUMP: "小程序跳立收通收银台",
            cls.WX_NATIVE:    "微信扫码（直连）",
            cls.YSF_BAR:   "云闪付条码",
            cls.YSF_JSAPI: "云闪付线上（小程序或扫码）",
            cls.CUP_QR:    "云闪付扫码",
            cls.BANK_FAST:      "快捷支付",
            cls.BANK_FAST_JUMP: "快捷支付收银台",
            cls.APPLY:            "用户入驻",
            cls.BALANCE_PAY:      "余额支付",
            cls.BALANCE_QUERY:    "余额查询",
            cls.SETTLE_QUERY:     "结算查询",
            cls.BALANCE_WITHDRAW: "余额提现",
            cls.WITHDRAW_SINGLE_QUERY: "单笔提现查询",
            cls.WITHDRAW_QUERY: "批量提现查询",
        }

    @classmethod
    def payment_methods(cls):
        business = {
            cls.APPLY, cls.BALANCE_PAY, cls.BALANCE_QUERY, cls.SETTLE_QUERY,
            cls.BALANCE_WITHDRAW, cls.WITHDRAW_SINGLE_QUERY, cls.WITHDRAW_QUERY,
        }
        return {k: v for k, v in cls.all().items() if k not in business}

    @classmethod
    def is_valid(cls, way_code: str) -> bool:
        """判断 way_code 是否在平台清单中。"""
        return way_code in cls.all()
