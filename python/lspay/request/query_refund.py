"""退款查询 Request"""

from dataclasses import dataclass

from ..enums.endpoint import Endpoint
from ..exceptions import LsPayValidationException
from ..validation import optional_string
from .base import BaseRequest


@dataclass
class QueryRefundRequest(BaseRequest):
    mch_refund_no: str = ""
    order_refund_id: str = ""

    def get_function_code(self) -> str:
        return Endpoint.QUERY_REFUND

    @property
    def refund_order_id(self) -> str:
        return self.order_refund_id

    def get_response_class(self):
        from ..response import QueryRefundResponse
        return QueryRefundResponse

    def validate(self) -> None:
        if not self.mch_refund_no and not self.order_refund_id:
            raise LsPayValidationException(
                "mch_refund_no 与 order_refund_id 至少填写一个"
            )
        optional_string("mch_refund_no", self.mch_refund_no, 30)
        optional_string("order_refund_id", self.order_refund_id, 30)
