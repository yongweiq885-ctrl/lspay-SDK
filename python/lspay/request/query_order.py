"""订单查询 Request"""

from dataclasses import dataclass

from ..enums.endpoint import Endpoint
from ..exceptions import LsPayValidationException
from ..validation import optional_string
from .base import BaseRequest


@dataclass
class QueryOrderRequest(BaseRequest):
    mch_order_no: str = ""
    pay_order_id: str = ""

    def get_function_code(self) -> str:
        return Endpoint.QUERY_ORDER

    def get_response_class(self):
        from ..response import QueryOrderResponse
        return QueryOrderResponse

    def validate(self) -> None:
        if not self.mch_order_no and not self.pay_order_id:
            raise LsPayValidationException("mch_order_no 与 pay_order_id 至少填写一个")
        optional_string("mch_order_no", self.mch_order_no, 30)
        optional_string("pay_order_id", self.pay_order_id, 30)
