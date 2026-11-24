"""LsPayClient - typed requests and legacy dict calls."""

from typing import Mapping

from ..exceptions import LsPayValidationException
from ..request.base import BaseRequest
from ..request.factory import RequestFactory
from ..request.unified_order import UnifiedOrderRequest
from .cashier_form import CashierForm
from .ls_pay import LsPay
from .ls_pay_response import LsPayResponse


class LsPayClient:
    """把请求对象转换为接口调用。"""

    @staticmethod
    def execute(request, profile: str = "default") -> LsPayResponse:
        if request is None:
            raise LsPayValidationException("request 不能为 None")
        if isinstance(request, Mapping):
            function_code = request.get("funcCode", request.get("func_code", ""))
            params = request.get("params", {})
            if not function_code:
                raise LsPayValidationException("funcCode 不能为空")
            request = RequestFactory.from_dict(str(function_code), params)
        if not isinstance(request, BaseRequest):
            raise LsPayValidationException("request 必须是 BaseRequest 子类或 dict")
        request.validate()
        biz = request.to_map()
        return LsPay.post(
            request.get_function_code(),
            biz,
            profile,
            request.get_response_class(),
        )

    @staticmethod
    def post_request(request, profile: str = "default") -> LsPayResponse:
        return LsPayClient.execute(request, profile)

    @staticmethod
    def build_cashier_form(request: UnifiedOrderRequest, profile: str = "default") -> CashierForm:
        """生成 pay_data_type=cashier 的收银台表单，由用户浏览器提交。"""
        if not isinstance(request, UnifiedOrderRequest):
            raise LsPayValidationException("request 必须是 UnifiedOrderRequest")
        request.validate()
        biz = request.to_map()
        if not LsPay.is_cashier_order(biz.get("channel_extra")):
            raise LsPayValidationException("build_cashier_form 仅用于 channel_extra.pay_data_type=cashier")
        path = request.get_function_code()
        signed = LsPay.build_signed_params(path, biz, profile)
        return CashierForm(
            action=LsPay._build_url(path),
            fields={k: str(v) for k, v in signed.items()},
        )
