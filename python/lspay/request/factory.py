"""Central factory for legacy dict calls."""

from typing import Any, Dict, Type

from ..enums.endpoint import Endpoint
from ..enums.way_code import WayCode
from ..exceptions import LsPayValidationException
from .balance import BalanceQueryRequest
from .base import BaseRequest
from .delay_trans import (
    DelayTransConfirmRefundRequest,
    DelayTransConfirmRequest,
    DelayTransCreateTaskRequest,
    DelayTransUnconfirmQueryRequest,
)
from .electronic_account import (
    ElectronicAccountBalanceWithdrawRequest,
    ElectronicAccountBatchWithdrawQueryRequest,
    ElectronicAccountBusinessChangeRequest,
    ElectronicAccountBusinessOpenRequest,
    ElectronicAccountSettlementQueryRequest,
    ElectronicAccountSingleWithdrawQueryRequest,
    ElectronicAccountUserOpenRequest,
    ElectronicAccountUserQueryRequest,
)
from .query_order import QueryOrderRequest
from .query_refund import QueryRefundRequest
from .quick_pay import (
    QuickPayBindApplyRequest,
    QuickPayBindConfirmRequest,
    QuickPayConfirmRequest,
    QuickPaySmsCheckRequest,
    QuickPayUnbindRequest,
)
from .refund import RefundRequest
from .unified_order import BalancePayRequest, QuickPayApplyRequest, UnifiedOrderRequest


class RequestFactory:
    DEFINITIONS: Dict[str, Type[BaseRequest]] = {
        Endpoint.UNIFIED_ORDER: UnifiedOrderRequest,
        Endpoint.QUERY_ORDER: QueryOrderRequest,
        Endpoint.REFUND: RefundRequest,
        Endpoint.QUERY_REFUND: QueryRefundRequest,
        Endpoint.DELAY_TRANS_CONFIRM: DelayTransConfirmRequest,
        Endpoint.DELAY_TRANS_CONFIRM_REFUND: DelayTransConfirmRefundRequest,
        Endpoint.DELAY_TRANS_UNCONFIRM_QUERY: DelayTransUnconfirmQueryRequest,
        Endpoint.DELAY_TRANS_CREATE_TASK: DelayTransCreateTaskRequest,
        Endpoint.QUICK_PAY_BIND_APPLY: QuickPayBindApplyRequest,
        Endpoint.QUICK_PAY_BIND_CONFIRM: QuickPayBindConfirmRequest,
        Endpoint.QUICK_PAY_CONFIRM: QuickPayConfirmRequest,
        Endpoint.QUICK_PAY_UNBIND: QuickPayUnbindRequest,
        Endpoint.QUICK_PAY_SMS_CHECK: QuickPaySmsCheckRequest,
        Endpoint.BALANCE_QUERY: BalanceQueryRequest,
        Endpoint.ELECTRONIC_ACCOUNT_USER_OPEN: ElectronicAccountUserOpenRequest,
        Endpoint.ELECTRONIC_ACCOUNT_BUSINESS_OPEN: ElectronicAccountBusinessOpenRequest,
        Endpoint.ELECTRONIC_ACCOUNT_BUSINESS_CHANGE: ElectronicAccountBusinessChangeRequest,
        Endpoint.ELECTRONIC_ACCOUNT_USER_QUERY: ElectronicAccountUserQueryRequest,
        Endpoint.ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY: ElectronicAccountSettlementQueryRequest,
        Endpoint.ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW: ElectronicAccountBalanceWithdrawRequest,
        Endpoint.ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY: ElectronicAccountSingleWithdrawQueryRequest,
        Endpoint.ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY: ElectronicAccountBatchWithdrawQueryRequest,
    }

    @classmethod
    def supports(cls, function_code: str) -> bool:
        return function_code in cls.DEFINITIONS

    @classmethod
    def from_dict(cls, function_code: str, params: Dict[str, Any]) -> BaseRequest:
        if not isinstance(params, dict):
            raise LsPayValidationException("params 必须是 dict")
        request_class = cls.DEFINITIONS.get(function_code)
        if request_class is None:
            raise LsPayValidationException("不支持的 funcCode: %s" % function_code)
        if function_code == Endpoint.UNIFIED_ORDER:
            if params.get("way_code") == WayCode.BANK_FAST:
                request_class = QuickPayApplyRequest
            elif params.get("way_code") == WayCode.BALANCE_PAY:
                request_class = BalancePayRequest
        known = set(request_class.__dataclass_fields__) - {"extend_infos"}
        values = {key: value for key, value in params.items() if key in known}
        extra = {key: value for key, value in params.items() if key not in known}
        request = request_class(**values)
        request.extend_infos = extra
        return request
