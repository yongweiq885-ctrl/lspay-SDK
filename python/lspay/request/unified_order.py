"""统一下单请求。端点 /gateway/unifiedorder。"""

from dataclasses import dataclass, field
from typing import Any, Optional, Union

from ..enums.business import PayDataType
from ..enums.endpoint import Endpoint
from ..enums.way_code import WayCode
from ..exceptions import LsPayValidationException
from ..validation import (
    http_url, ipv4, json_dict, json_object, optional_string, required_string,
    validate_division_list,
)
from .base import BaseRequest


@dataclass
class UnifiedOrderRequest(BaseRequest):
    mch_order_no: str = ""
    way_code: str = ""
    amount: Optional[int] = None  # 单位：分
    currency: str = "cny"
    subject: str = ""
    body: str = ""
    channel_extra: Union[str, dict, None] = None
    client_ip: str = ""
    notify_url: str = ""
    return_url: str = ""
    expired_time: str = ""
    division_mode: Optional[int] = None
    ext_param: Union[str, dict, None] = None
    polling_mode: Optional[int] = None

    def get_function_code(self) -> str:
        return Endpoint.UNIFIED_ORDER

    def get_response_class(self):
        from ..response import UnifiedOrderResponse
        return UnifiedOrderResponse

    def validate(self) -> None:
        required_string("mch_order_no", self.mch_order_no, 30)
        required_string("way_code", self.way_code, 30)
        if self.way_code not in self.allowed_way_codes():
            raise LsPayValidationException("way_code 不在统一下单支付方式清单中")
        if isinstance(self.amount, bool) or not isinstance(self.amount, int) or self.amount <= 0:
            raise LsPayValidationException("amount 必须是大于 0 的整数（单位：分）")
        if len(str(self.currency)) != 3 or not str(self.currency).isalpha():
            raise LsPayValidationException("currency 必须是 3 位字母货币代码")
        required_string("subject", self.subject, 64)
        required_string("body", self.body, 256)
        optional_string("client_ip", self.client_ip, 32)
        optional_string("ext_param", self._normalize_ext_param(), 512)
        ipv4("client_ip", self.client_ip)
        http_url("notify_url", self.notify_url)
        http_url("return_url", self.return_url)
        if self.expired_time not in (None, ""):
            if isinstance(self.expired_time, bool) or not str(self.expired_time).isdigit() or int(self.expired_time) <= 0:
                raise LsPayValidationException("expired_time 必须是大于 0 的秒数")
            self.expired_time = int(self.expired_time)
        if self.division_mode not in (None, 0, 1, 2):
            raise LsPayValidationException("division_mode 只允许 0、1、2")
        if self.polling_mode not in (None, 0, 1, 2, 3):
            raise LsPayValidationException("polling_mode 只允许 0、1、2、3")
        if self.channel_extra is None or (isinstance(self.channel_extra, str) and not self.channel_extra.strip()):
            raise LsPayValidationException("channel_extra 必填")
        extra = json_dict(self.channel_extra)
        self.channel_extra = json_object(extra)
        required = {
            WayCode.ALI_LITE: "buyer_id", WayCode.WX_LITE: "openid",
            WayCode.WX_BAR: "auth_code", WayCode.ALI_BAR: "auth_code",
            WayCode.YSF_BAR: "auth_code",
        }
        if not str(extra.get("pay_data_type", "")).strip():
            required[WayCode.ALI_PUB] = "buyer_id"
            required[WayCode.WX_PUB] = "openid"
        field_name = required.get(self.way_code)
        if field_name and not str(extra.get(field_name, "")).strip():
            raise LsPayValidationException(
                "way_code=%s 时 channel_extra.%s 必填" % (self.way_code, field_name)
            )
        if self.way_code == WayCode.BANK_FAST:
            for name in ("channel_user", "channel_card_id"):
                required_string("channel_extra." + name, extra.get(name), 256)
        if "pay_data_type" in extra and extra["pay_data_type"] not in PayDataType.all():
            raise LsPayValidationException("channel_extra.pay_data_type 非法")
        if "div_list" in extra:
            validate_division_list(extra["div_list"])
        if self.division_mode in (1, 2) and not extra.get("div_list"):
            raise LsPayValidationException(
                "division_mode 为 1 或 2 时 channel_extra.div_list 必填且不能为空"
            )

    def allowed_way_codes(self):
        return WayCode.payment_methods()

    def _normalize_ext_param(self) -> Any:
        if isinstance(self.ext_param, dict):
            self.ext_param = json_object(self.ext_param, "ext_param")
        return self.ext_param


@dataclass
class QuickPayApplyRequest(UnifiedOrderRequest):
    way_code: str = WayCode.BANK_FAST

    def get_response_class(self):
        from ..response import QuickPayApplyResponse
        return QuickPayApplyResponse

    def validate(self) -> None:
        super().validate()
        if self.way_code != WayCode.BANK_FAST:
            raise LsPayValidationException("way_code 固定为 BANK_FAST")


@dataclass
class BalancePayRequest(UnifiedOrderRequest):
    way_code: str = WayCode.BALANCE_PAY
    division_mode: Optional[int] = 1

    def get_response_class(self):
        from ..response import BalancePayResponse
        return BalancePayResponse

    def allowed_way_codes(self):
        return {WayCode.BALANCE_PAY: "余额支付"}

    def validate(self) -> None:
        super().validate()
        if self.way_code != WayCode.BALANCE_PAY or self.division_mode != 1:
            raise LsPayValidationException("余额支付固定 way_code=BALANCE_PAY、division_mode=1")
        extra = json_dict(self.channel_extra)
        required_string("channel_extra.trans_out", extra.get("trans_out"), 256)
        total = 0
        for item in extra.get("div_list", []):
            amount = item.get("div_amt")
            if isinstance(amount, bool) or not isinstance(amount, int) or amount <= 0:
                raise LsPayValidationException("余额支付 div_amt 必填且必须为正整数")
            total += amount
        if total > int(self.amount or 0):
            raise LsPayValidationException("channel_extra.div_list 的 div_amt 总和不能超过 amount")
