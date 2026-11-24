"""Balance query request."""

from dataclasses import dataclass

from ..enums.endpoint import Endpoint
from ..enums.way_code import WayCode
from ..exceptions import LsPayValidationException
from ..validation import ipv4, required_string
from .base import BaseRequest
from .unified_order import BalancePayRequest


@dataclass
class BalanceQueryRequest(BaseRequest):
    order_id: str = ""
    way_code: str = WayCode.BALANCE_QUERY
    client_ip: str = ""
    account_no: str = ""

    def get_function_code(self): return Endpoint.BALANCE_QUERY

    def get_response_class(self):
        from ..response import BalanceQueryResponse
        return BalanceQueryResponse

    def validate(self):
        required_string("order_id", self.order_id, 30)
        if self.way_code != WayCode.BALANCE_QUERY:
            raise LsPayValidationException("way_code 固定为 BALANCE_QUERY")
        required_string("account_no", self.account_no, 256)
        ipv4("client_ip", self.client_ip)


__all__ = ["BalancePayRequest", "BalanceQueryRequest"]
