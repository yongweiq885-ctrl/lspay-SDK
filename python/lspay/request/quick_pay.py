"""Quick-pay requests."""

import re
from dataclasses import dataclass
from typing import Union

from ..enums.endpoint import Endpoint
from ..enums.way_code import WayCode
from ..exceptions import LsPayValidationException
from ..validation import ipv4, json_dict, json_object, optional_string, required_string
from .base import BaseRequest
from .channel_extra import (
    QuickPayBindChannelExtra,
    QuickPayBindConfirmChannelExtra,
    QuickPayUnbindChannelExtra,
)
from .unified_order import QuickPayApplyRequest


@dataclass
class QuickPayBindApplyRequest(BaseRequest):
    order_id: str = ""
    way_code: str = WayCode.BANK_FAST
    client_ip: str = ""
    channel_extra: Union[str, dict, QuickPayBindChannelExtra, None] = None

    def get_function_code(self): return Endpoint.QUICK_PAY_BIND_APPLY

    def get_response_class(self):
        from ..response import QuickPayBindApplyResponse
        return QuickPayBindApplyResponse

    def validate(self):
        _common(self.order_id, self.way_code, self.client_ip)
        extra = json_dict(self.channel_extra)
        QuickPayBindChannelExtra(**extra).to_dict()
        self.channel_extra = json_object(extra)
        optional_string("channel_extra", self.channel_extra, 256)


@dataclass
class QuickPayBindConfirmRequest(BaseRequest):
    order_id: str = ""
    way_code: str = WayCode.BANK_FAST
    client_ip: str = ""
    channel_extra: Union[str, dict, QuickPayBindConfirmChannelExtra, None] = None
    verify_code: str = ""

    def get_function_code(self): return Endpoint.QUICK_PAY_BIND_CONFIRM

    def get_response_class(self):
        from ..response import QuickPayBindConfirmResponse
        return QuickPayBindConfirmResponse

    def validate(self):
        _common(self.order_id, self.way_code, self.client_ip)
        extra = json_dict(self.channel_extra)
        QuickPayBindConfirmChannelExtra(**extra).to_dict()
        self.channel_extra = json_object(extra)
        if not re.match(r"^\d{3,8}$", str(self.verify_code)):
            raise LsPayValidationException("verify_code 必须是 3-8 位数字")


@dataclass
class QuickPayConfirmRequest(BaseRequest):
    way_code: str = WayCode.BANK_FAST
    client_ip: str = ""
    pay_order_id: str = ""
    mch_order_no: str = ""
    verify_code: str = ""

    def get_function_code(self): return Endpoint.QUICK_PAY_CONFIRM

    def get_response_class(self):
        from ..response import QuickPayConfirmResponse
        return QuickPayConfirmResponse

    def validate(self):
        if self.way_code != WayCode.BANK_FAST:
            raise LsPayValidationException("way_code 固定为 BANK_FAST")
        ipv4("client_ip", self.client_ip)
        optional_string("pay_order_id", self.pay_order_id, 30)
        optional_string("mch_order_no", self.mch_order_no, 30)
        if not self.pay_order_id and not self.mch_order_no:
            raise LsPayValidationException("pay_order_id 与 mch_order_no 至少填写一个")
        if not re.match(r"^\d{6}$", str(self.verify_code)):
            raise LsPayValidationException("verify_code 必须是 6 位数字")


@dataclass
class QuickPaySmsCheckRequest(QuickPayConfirmRequest):
    def get_function_code(self): return Endpoint.QUICK_PAY_SMS_CHECK

    def get_response_class(self):
        from ..response import QuickPaySmsCheckResponse
        return QuickPaySmsCheckResponse


@dataclass
class QuickPayUnbindRequest(BaseRequest):
    order_id: str = ""
    way_code: str = WayCode.BANK_FAST
    client_ip: str = ""
    channel_extra: Union[str, dict, QuickPayUnbindChannelExtra, None] = None

    def get_function_code(self): return Endpoint.QUICK_PAY_UNBIND

    def get_response_class(self):
        from ..response import QuickPayUnbindResponse
        return QuickPayUnbindResponse

    def validate(self):
        _common(self.order_id, self.way_code, self.client_ip)
        extra = json_dict(self.channel_extra)
        QuickPayUnbindChannelExtra(
            mch_user_id=extra.get("mch_user_id", ""),
            channel_card_id=extra.get("channel_card_id", ""),
        ).to_dict()
        self.channel_extra = json_object(extra)


def _common(order_id, way_code, client_ip):
    required_string("order_id", order_id, 30)
    if way_code != WayCode.BANK_FAST:
        raise LsPayValidationException("way_code 固定为 BANK_FAST")
    ipv4("client_ip", client_ip)


__all__ = [
    "QuickPayApplyRequest", "QuickPayBindApplyRequest",
    "QuickPayBindConfirmRequest", "QuickPayConfirmRequest",
    "QuickPaySmsCheckRequest", "QuickPayUnbindRequest",
]
