"""退款 Request"""

from dataclasses import dataclass
from typing import Any, Optional, Union

from ..enums.endpoint import Endpoint
from ..exceptions import LsPayValidationException
from ..validation import http_url, ipv4, json_object, optional_string, required_string
from .base import BaseRequest


@dataclass
class RefundRequest(BaseRequest):
    mch_refund_no: str = ""
    mch_order_no: str = ""
    pay_order_id: str = ""
    refund_amount: Optional[int] = None
    currency: str = "cny"
    refund_reason: str = ""
    client_ip: str = ""
    notify_url: str = ""
    channel_extra: Union[str, dict, None] = None
    ext_param: Any = None

    def get_function_code(self) -> str:
        return Endpoint.REFUND

    def get_response_class(self):
        from ..response import RefundResponse
        return RefundResponse

    def validate(self) -> None:
        required_string("mch_refund_no", self.mch_refund_no, 30)
        optional_string("mch_order_no", self.mch_order_no, 30)
        optional_string("pay_order_id", self.pay_order_id, 30)
        if not self.mch_order_no and not self.pay_order_id:
            raise LsPayValidationException("mch_order_no 与 pay_order_id 至少填写一个")
        if isinstance(self.refund_amount, bool) or not isinstance(self.refund_amount, int) or self.refund_amount <= 0:
            raise LsPayValidationException("refund_amount 必须是大于 0 的整数（单位：分）")
        if len(str(self.currency)) != 3 or not str(self.currency).isalpha():
            raise LsPayValidationException("currency 必须是 3 位字母货币代码")
        required_string("refund_reason", self.refund_reason, 64)
        ipv4("client_ip", self.client_ip)
        http_url("notify_url", self.notify_url)
        if self.channel_extra not in (None, ""):
            self.channel_extra = json_object(self.channel_extra)
            optional_string("channel_extra", self.channel_extra, 256)
        if isinstance(self.ext_param, dict):
            self.ext_param = json_object(self.ext_param, "ext_param")
        optional_string("ext_param", self.ext_param, 512)
