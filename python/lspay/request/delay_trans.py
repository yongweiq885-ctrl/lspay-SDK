"""Delayed-settlement requests."""

from dataclasses import dataclass
from typing import Any, Optional, Union

from ..enums.endpoint import Endpoint
from ..exceptions import LsPayValidationException
from ..validation import (
    http_url, json_dict, json_object, optional_string, required_string,
    validate_division_list,
)
from .base import BaseRequest


@dataclass
class DelayTransConfirmRequest(BaseRequest):
    pay_order_id: str = ""
    mch_order_no: str = ""
    order_id: str = ""
    channel_extra: Union[str, dict, None] = None

    def get_function_code(self): return Endpoint.DELAY_TRANS_CONFIRM

    def get_response_class(self):
        from ..response import DelayTransConfirmResponse
        return DelayTransConfirmResponse

    def validate(self):
        optional_string("pay_order_id", self.pay_order_id, 30)
        optional_string("mch_order_no", self.mch_order_no, 30)
        if not self.pay_order_id and not self.mch_order_no:
            raise LsPayValidationException("pay_order_id 与 mch_order_no 至少填写一个")
        required_string("order_id", self.order_id, 30)
        if self.channel_extra not in (None, ""):
            extra = json_dict(self.channel_extra)
            self.channel_extra = json_object(extra)
            optional_string("channel_extra", self.channel_extra, 256)
            if "div_list" in extra:
                validate_division_list(extra["div_list"], required=True)


@dataclass
class DelayTransConfirmRefundRequest(BaseRequest):
    confirm_order_id: str = ""
    confirm_refund_no: str = ""
    channel_extra: Union[str, dict, None] = None

    def get_function_code(self): return Endpoint.DELAY_TRANS_CONFIRM_REFUND

    def get_response_class(self):
        from ..response import DelayTransConfirmRefundResponse
        return DelayTransConfirmRefundResponse

    def validate(self):
        required_string("confirm_order_id", self.confirm_order_id, 30)
        required_string("confirm_refund_no", self.confirm_refund_no, 30)
        if self.channel_extra not in (None, ""):
            self.channel_extra = json_object(self.channel_extra)
            optional_string("channel_extra", self.channel_extra, 256)


@dataclass
class DelayTransUnconfirmQueryRequest(BaseRequest):
    def get_function_code(self): return Endpoint.DELAY_TRANS_UNCONFIRM_QUERY

    def get_response_class(self):
        from ..response import DelayTransUnconfirmQueryResponse
        return DelayTransUnconfirmQueryResponse


@dataclass
class DelayTransCreateTaskRequest(BaseRequest):
    account_no: str = ""
    account_name: str = ""
    order_no: str = ""
    amount: Optional[int] = None
    notify_url: str = ""
    remark: str = ""

    def get_function_code(self): return Endpoint.DELAY_TRANS_CREATE_TASK

    def get_response_class(self):
        from ..response import DelayTransCreateTaskResponse
        return DelayTransCreateTaskResponse

    def validate(self):
        required_string("account_no", self.account_no, 30)
        required_string("account_name", self.account_name, 30)
        required_string("order_no", self.order_no, 30)
        if isinstance(self.amount, bool) or not isinstance(self.amount, int) or self.amount <= 0:
            raise LsPayValidationException("amount 必须是大于 0 的整数（单位：分）")
        optional_string("notify_url", self.notify_url, 64)
        optional_string("remark", self.remark, 256)
        http_url("notify_url", self.notify_url, 64)
