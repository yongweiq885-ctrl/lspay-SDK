"""Electronic-account requests."""

import re
from dataclasses import dataclass
from typing import Any, Optional, Union

from ..enums.business import ElectronicAccountRelationType, ElectronicAccountType
from ..enums.endpoint import Endpoint
from ..enums.way_code import WayCode
from ..exceptions import LsPayValidationException
from ..validation import date_ymd, ipv4, json_dict, json_object, positive_int, required_string
from .base import BaseRequest
from .channel_extra import (
    ElectronicAccountBusinessChangeChannelExtra,
    ElectronicAccountBusinessChannelExtra,
    ElectronicAccountCashConfig,
    ElectronicAccountMerchantInfo,
    ElectronicAccountModifiedCard,
    ElectronicAccountOpenChannelExtra,
    ElectronicAccountSettleConfig,
    ElectronicAccountSettlementCard,
    ElectronicAccountUserInfo,
)


@dataclass
class ElectronicAccountUserOpenRequest(BaseRequest):
    way_code: str = WayCode.APPLY
    order_id: str = ""
    account_name: str = ""
    account_type: Optional[int] = None
    relation_type: str = ""
    relation_type_name: str = ""
    account_alias: str = ""
    division_profit: str = ""
    client_ip: str = ""
    channel_extra: Any = None

    def get_function_code(self): return Endpoint.ELECTRONIC_ACCOUNT_USER_OPEN

    def get_response_class(self):
        from ..response import ElectronicAccountUserOpenResponse
        return ElectronicAccountUserOpenResponse

    def validate(self):
        if self.way_code != WayCode.APPLY:
            raise LsPayValidationException("way_code 固定为 APPLY")
        required_string("order_id", self.order_id, 30)
        required_string("account_name", self.account_name, 30)
        if self.account_type not in ElectronicAccountType.all():
            raise LsPayValidationException("account_type 只允许 1、2、3")
        required_string("relation_type", self.relation_type, 30)
        if self.relation_type not in ElectronicAccountRelationType.all():
            raise LsPayValidationException("relation_type 非法")
        if self.relation_type == ElectronicAccountRelationType.CUSTOM:
            required_string("relation_type_name", self.relation_type_name, 30)
        required_string("account_alias", self.account_alias, 30)
        if not re.match(r"^(?:0(?:\.\d+)?|1(?:\.0+)?)$", str(self.division_profit)):
            raise LsPayValidationException("division_profit 必须是 0 到 1 之间的十进制比例")
        ipv4("client_ip", self.client_ip)
        extra = json_dict(self.channel_extra)
        if self.account_type == ElectronicAccountType.PERSONAL:
            if not isinstance(extra.get("user_info"), dict):
                raise LsPayValidationException("account_type=1 时 channel_extra.user_info 必填")
            ElectronicAccountUserInfo(**extra["user_info"]).to_dict()
        else:
            if not isinstance(extra.get("mer_info"), dict):
                raise LsPayValidationException("account_type=2 或 3 时 channel_extra.mer_info 必填")
            ElectronicAccountMerchantInfo(**extra["mer_info"]).to_dict()
        self.channel_extra = json_object(extra)


@dataclass
class _BusinessRequest(BaseRequest):
    order_id: str = ""
    account_no: str = ""
    client_ip: str = ""
    channel_extra: Any = None

    CHANGE = False

    def validate(self):
        required_string("order_id", self.order_id, 30)
        required_string("account_no", self.account_no, 32)
        ipv4("client_ip", self.client_ip)
        extra = json_dict(self.channel_extra)
        card = extra.get("card_info")
        settle = extra.get("settle_config")
        cash = extra.get("cash_config")
        if not isinstance(card, dict) or not isinstance(settle, dict):
            raise LsPayValidationException("channel_extra.card_info 与 settle_config 必填")
        if not isinstance(cash, list) or not cash:
            raise LsPayValidationException("channel_extra.cash_config 必须是非空数组列表")
        if self.CHANGE:
            ElectronicAccountModifiedCard(
                card_type=card.get("card_type", ""),
                card_name=card.get("card_name", ""),
                card_no=card.get("card_no", ""),
            ).to_dict()
        else:
            ElectronicAccountSettlementCard(**card).to_dict()
        ElectronicAccountSettleConfig(**settle).to_dict()
        for item in cash:
            if not isinstance(item, dict):
                raise LsPayValidationException("channel_extra.cash_config 每项必须是对象")
            ElectronicAccountCashConfig(**item).to_dict()
        self.channel_extra = json_object(extra)


@dataclass
class ElectronicAccountBusinessOpenRequest(_BusinessRequest):
    way_code: str = WayCode.APPLY

    def get_function_code(self): return Endpoint.ELECTRONIC_ACCOUNT_BUSINESS_OPEN

    def get_response_class(self):
        from ..response import ElectronicAccountBusinessOpenResponse
        return ElectronicAccountBusinessOpenResponse

    def validate(self):
        if self.way_code != WayCode.APPLY:
            raise LsPayValidationException("way_code 固定为 APPLY")
        super().validate()


@dataclass
class ElectronicAccountBusinessChangeRequest(_BusinessRequest):
    CHANGE = True
    way_code: str = WayCode.APPLY

    def get_function_code(self): return Endpoint.ELECTRONIC_ACCOUNT_BUSINESS_CHANGE

    def get_response_class(self):
        from ..response import ElectronicAccountBusinessChangeResponse
        return ElectronicAccountBusinessChangeResponse

    def validate(self):
        if self.way_code != WayCode.APPLY:
            raise LsPayValidationException("way_code 固定为 APPLY")
        super().validate()


@dataclass
class ElectronicAccountUserQueryRequest(BaseRequest):
    way_code: str = WayCode.APPLY
    account_no: str = ""
    client_ip: str = ""

    def get_function_code(self): return Endpoint.ELECTRONIC_ACCOUNT_USER_QUERY

    def get_response_class(self):
        from ..response import ElectronicAccountUserQueryResponse
        return ElectronicAccountUserQueryResponse

    def validate(self):
        if self.way_code != WayCode.APPLY:
            raise LsPayValidationException("way_code 固定为 APPLY")
        required_string("account_no", self.account_no, 32)
        ipv4("client_ip", self.client_ip)


@dataclass
class ElectronicAccountSettlementQueryRequest(BaseRequest):
    order_id: str = ""
    way_code: str = WayCode.SETTLE_QUERY
    account_no: str = ""
    begin_date: str = ""
    end_date: str = ""
    page_size: Any = None
    page_num: Any = None

    def get_function_code(self): return Endpoint.ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY

    def get_response_class(self):
        from ..response import ElectronicAccountSettlementQueryResponse
        return ElectronicAccountSettlementQueryResponse

    def validate(self):
        required_string("order_id", self.order_id, 30)
        if self.way_code != WayCode.SETTLE_QUERY:
            raise LsPayValidationException("way_code 固定为 SETTLE_QUERY")
        required_string("account_no", self.account_no, 32)
        begin, end = date_ymd("begin_date", self.begin_date), date_ymd("end_date", self.end_date)
        if begin > end or (end - begin).days > 30:
            raise LsPayValidationException("结算查询日期顺序错误或跨度超过 30 天")
        positive_int("page_size", self.page_size, optional=True)
        positive_int("page_num", self.page_num, optional=True)


@dataclass
class ElectronicAccountBalanceWithdrawRequest(BaseRequest):
    order_id: str = ""
    way_code: str = WayCode.BALANCE_WITHDRAW
    account_no: str = ""
    withdraw_amount: Any = None

    def get_function_code(self): return Endpoint.ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW

    def get_response_class(self):
        from ..response import ElectronicAccountBalanceWithdrawResponse
        return ElectronicAccountBalanceWithdrawResponse

    def validate(self):
        required_string("order_id", self.order_id, 30)
        if self.way_code != WayCode.BALANCE_WITHDRAW:
            raise LsPayValidationException("way_code 固定为 BALANCE_WITHDRAW")
        required_string("account_no", self.account_no, 32)
        value = str(self.withdraw_amount or "")
        if len(value) > 32 or not re.match(r"^(?:0|[1-9]\d*)(?:\.\d{1,2})?$", value) or not re.search(r"[1-9]", value):
            raise LsPayValidationException("withdraw_amount 必须是大于 0 且最多保留 2 位小数的元金额")
        self.withdraw_amount = value


@dataclass
class ElectronicAccountSingleWithdrawQueryRequest(BaseRequest):
    way_code: str = WayCode.WITHDRAW_SINGLE_QUERY
    account_no: str = ""
    withdraw_date: str = ""
    order_id: str = ""

    def get_function_code(self): return Endpoint.ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY

    def get_response_class(self):
        from ..response import ElectronicAccountSingleWithdrawQueryResponse
        return ElectronicAccountSingleWithdrawQueryResponse

    def validate(self):
        if self.way_code != WayCode.WITHDRAW_SINGLE_QUERY:
            raise LsPayValidationException("way_code 固定为 WITHDRAW_SINGLE_QUERY")
        required_string("account_no", self.account_no, 32)
        date_ymd("withdraw_date", self.withdraw_date)
        required_string("order_id", self.order_id, 30)


@dataclass
class ElectronicAccountBatchWithdrawQueryRequest(BaseRequest):
    way_code: str = WayCode.WITHDRAW_QUERY
    account_no: str = ""
    begin_date: str = ""
    end_date: str = ""
    page_size: Any = None
    page_num: Any = None

    def get_function_code(self): return Endpoint.ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY

    def get_response_class(self):
        from ..response import ElectronicAccountBatchWithdrawQueryResponse
        return ElectronicAccountBatchWithdrawQueryResponse

    def validate(self):
        if self.way_code != WayCode.WITHDRAW_QUERY:
            raise LsPayValidationException("way_code 固定为 WITHDRAW_QUERY")
        required_string("account_no", self.account_no, 32)
        begin, end = date_ymd("begin_date", self.begin_date), date_ymd("end_date", self.end_date)
        if begin > end:
            raise LsPayValidationException("begin_date 不能晚于 end_date")
        positive_int("page_size", self.page_size, optional=True)
        positive_int("page_num", self.page_num, optional=True)


__all__ = [name for name in list(globals()) if name.startswith("ElectronicAccount")]
