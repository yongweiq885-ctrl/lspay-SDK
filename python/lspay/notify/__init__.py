"""Verified payment, refund and delayed-transfer notifications."""

import hmac
import re
from typing import Any, Dict, Mapping, Optional

from ..config import MerConfig
from ..core.ls_pay import LsPay
from ..core.ls_pay_tools import LsPayTools
from ..enums.business import DelayTransTaskStatus, OrderState, RefundState
from ..exceptions import LsPaySignException, LsPayValidationException
from ..validation import datetime_value, ipv4


class BaseNotify:
    def __init__(self, data: Mapping[str, Any], config: MerConfig):
        if not isinstance(data, Mapping) or not data:
            raise LsPayValidationException("通知参数不能为空")
        self.data = dict(data)
        for key, value in self.data.items():
            if not isinstance(key, str) or not key:
                raise LsPayValidationException("通知参数名必须是非空字符串")
            if isinstance(value, (dict, list, tuple, set)):
                raise LsPayValidationException("通知参数 %s 必须是标量值" % key)
        sign = str(self.data.get("sign", ""))
        if not re.match(r"^[A-Fa-f0-9]{32}$", sign):
            raise LsPaySignException("通知缺少合法的 32 位 MD5 签名")
        expected = LsPayTools.sign(self.data, config.md5_key)
        if not hmac.compare_digest(expected.upper(), sign.upper()):
            raise LsPaySignException("通知签名验证失败")
        mch_no, app_id = self._required("mch_no", 30), self._required("app_id", 24)
        if not hmac.compare_digest(str(config.mch_no), mch_no) or not hmac.compare_digest(str(config.app_id), app_id):
            raise LsPayValidationException("通知商户身份与 SDK 配置不匹配")

    def get_raw(self): return dict(self.data)
    def get_notify_type(self): return self._value("notify_type")
    def get_event_type(self): return self._value("event_type")
    def get_pay_order_id(self): return self._value("pay_order_id")
    def get_mch_no(self): return self._value("mch_no")
    def get_app_id(self): return self._value("app_id")
    def get_way_code(self): return self._value("way_code")
    def get_currency(self): return self._value("currency")
    def get_ext_param(self): return self._value("ext_param")
    def get_add_time(self): return self._value("add_time")
    def get_success_time(self): return self._value("success_time")
    def get_req_time(self): return self._value("req_time")
    def get_sign(self): return self._value("sign")
    def get_state(self): return self._integer("state")

    def _value(self, key: str) -> Optional[str]:
        value = self.data.get(key)
        return None if value is None else str(value)

    def _integer(self, key: str) -> Optional[int]:
        value = self._value(key)
        return int(value) if value is not None and re.match(r"^\d+$", value) else None

    def _required(self, field: str, max_length: int, allow_empty: bool = False) -> str:
        if field not in self.data:
            raise LsPayValidationException("通知缺少 %s" % field)
        value = str(self.data[field])
        if not allow_empty and not value.strip():
            raise LsPayValidationException("通知字段 %s 不能为空" % field)
        if len(value) > max_length:
            raise LsPayValidationException("通知字段 %s 长度超限" % field)
        return value

    def _positive(self, field: str) -> int:
        value = self._required(field, 20)
        if not re.match(r"^\d+$", value) or int(value) <= 0:
            raise LsPayValidationException("通知字段 %s 必须是大于 0 的整数" % field)
        return int(value)

    def _digits(self, field: str, minimum: int, maximum: int) -> str:
        value = self._required(field, maximum)
        if not value.isdigit() or not minimum <= len(value) <= maximum:
            span = str(minimum) if minimum == maximum else "%s-%s" % (minimum, maximum)
            raise LsPayValidationException("通知字段 %s 必须是 %s 位数字" % (field, span))
        return value

    def _assert_expected(self, field: str, expected: Any):
        actual = self._value(field)
        if actual is None or not hmac.compare_digest(str(expected), actual):
            raise LsPayValidationException("通知字段 %s 与商户订单记录不一致" % field)


class PaymentNotify(BaseNotify):
    def __init__(self, data: Mapping[str, Any], config: MerConfig):
        super().__init__(data, config)
        for field, length in (
            ("pay_order_id", 30), ("mch_order_no", 30), ("way_code", 30),
            ("subject", 64), ("body", 256),
        ):
            self._required(field, length)
        self._positive("amount")
        currency = self._required("currency", 3)
        if not re.match(r"^[A-Za-z]{3}$", currency):
            raise LsPayValidationException("通知字段 currency 必须是 3 位字母货币代码")
        if self.get_state() not in range(7):
            raise LsPayValidationException("通知字段 state 非法")
        self._digits("add_time", 10, 10)
        if self._value("success_time"):
            self._digits("success_time", 10, 10)
        self._digits("req_time", 10, 13)
        if self._value("client_ip"):
            ipv4("通知字段 client_ip", self._value("client_ip"))
        _event_prefix(self, "payment")

    def get_mch_order_no(self): return self._value("mch_order_no")
    def get_amount(self): return self._integer("amount")
    def is_succeeded(self):
        return self.get_state() == OrderState.PAID and self.get_event_type() in (None, "payment.succeeded")

    def assert_order(self, mch_order_no: str, amount: int, currency: str = "cny"):
        self._assert_expected("mch_order_no", mch_order_no)
        self._assert_expected("amount", amount)
        self._assert_expected("currency", currency.lower())
        return self

    def get_idempotency_key(self):
        return "payment:%s:%s" % (
            self.get_pay_order_id(), self.get_event_type() or self.get_state()
        )


class RefundNotify(BaseNotify):
    def __init__(self, data: Mapping[str, Any], config: MerConfig):
        super().__init__(data, config)
        if self._required("notify_type", 64) != "refund":
            raise LsPayValidationException("通知字段 notify_type 必须是 refund")
        self._required("event_type", 64)
        _event_prefix(self, "refund")
        for field, length in (("pay_order_id", 30), ("mch_refund_no", 30), ("way_code", 30)):
            self._required(field, length)
        pay_amount, refund_amount = self._positive("pay_amount"), self._positive("refund_amount")
        if refund_amount > pay_amount:
            raise LsPayValidationException("通知字段 refund_amount 不能大于 pay_amount")
        if self.get_state() not in range(5):
            raise LsPayValidationException("通知字段 state 非法")
        if not re.match(r"^[A-Za-z]{3}$", self._required("currency", 3)):
            raise LsPayValidationException("通知字段 currency 非法")
        datetime_value("通知字段 add_time", self._required("add_time", 19))
        datetime_value("通知字段 success_time", self._required("success_time", 19))
        self._digits("req_time", 10, 10)

    def get_mch_refund_no(self): return self._value("mch_refund_no")
    def get_pay_amount(self): return self._integer("pay_amount")
    def get_refund_amount(self): return self._integer("refund_amount")
    def is_succeeded(self):
        return self.get_state() == RefundState.SUCCEEDED and self.get_event_type() == "refund.succeeded"

    def assert_refund(self, number: str, amount: int, currency: str = "cny", pay_amount=None):
        self._assert_expected("mch_refund_no", number)
        self._assert_expected("refund_amount", amount)
        self._assert_expected("currency", currency.lower())
        if pay_amount is not None:
            self._assert_expected("pay_amount", pay_amount)
        return self

    def get_idempotency_key(self):
        return "refund:%s:%s" % (self.get_mch_refund_no(), self.get_event_type())


class DelayTransTransferNotify(BaseNotify):
    def __init__(self, data: Mapping[str, Any], config: MerConfig):
        super().__init__(data, config)
        for field, length in (("task_id", 64), ("order_no", 30), ("account_no", 64), ("account_name", 128)):
            self._required(field, length)
        target = self._positive("target_amount")
        processed, remaining = self._non_negative("processed_amount"), self._non_negative("remaining_amount")
        status = self._required("status", 16)
        if status not in DelayTransTaskStatus.all():
            raise LsPayValidationException("通知字段 status 非法")
        if processed > target or remaining > target:
            raise LsPayValidationException("通知金额不能超过 target_amount")
        try:
            progress = float(self._required("progress", 8))
        except ValueError:
            raise LsPayValidationException("通知字段 progress 必须是数字")
        if not 0 <= progress <= 100:
            raise LsPayValidationException("通知字段 progress 必须是 0.0-100.0 的数字")
        if status in (DelayTransTaskStatus.PENDING, DelayTransTaskStatus.FAILED):
            if processed != 0 or remaining != target:
                raise LsPayValidationException("PENDING/FAILED 状态的通知金额关系非法")
        if status in (DelayTransTaskStatus.PROCESSING, DelayTransTaskStatus.PARTIAL):
            if processed <= 0 or processed >= target or remaining != target - processed:
                raise LsPayValidationException("PROCESSING/PARTIAL 状态的通知金额关系非法")

    def _non_negative(self, field):
        value = self._required(field, 20)
        if not value.isdigit():
            raise LsPayValidationException("通知字段 %s 必须是非负整数" % field)
        return int(value)

    def get_task_id(self): return self._value("task_id")
    def get_order_no(self): return self._value("order_no")
    def get_target_amount(self): return self._integer("target_amount")
    def get_processed_amount(self): return self._integer("processed_amount")
    def get_remaining_amount(self): return self._integer("remaining_amount")
    def get_task_status(self): return self._value("status")
    def is_succeeded(self): return self.get_task_status() == DelayTransTaskStatus.COMPLETED
    def is_partial(self): return self.get_task_status() == DelayTransTaskStatus.PARTIAL
    def assert_task(self, order_no: str, target_amount: int):
        self._assert_expected("order_no", order_no)
        self._assert_expected("target_amount", target_amount)
        return self
    def get_idempotency_key(self):
        return "delaytrans-transfer:%s:%s:%s" % (
            self.get_task_id(), self.get_task_status(), self.get_processed_amount()
        )


class LsPayNotify:
    SUCCESS_RESPONSE = "ok"
    FAILURE_RESPONSE = "fail"

    @classmethod
    def parse(cls, params: Mapping[str, Any], profile: str = "default"):
        notify_type = str(params.get("notify_type", ""))
        event_type = str(params.get("event_type", ""))
        if notify_type == "refund" or event_type.startswith("refund."):
            return cls.parse_refund(params, profile)
        if notify_type.startswith("payment") or event_type.startswith("payment."):
            return cls.parse_payment(params, profile)
        transfer_fields = {"task_id", "order_no", "target_amount", "processed_amount", "remaining_amount"}
        if transfer_fields.issubset(params):
            return cls.parse_delay_trans_transfer(params, profile)
        raise LsPayValidationException("无法根据 notify_type / event_type 识别通知类型")

    @staticmethod
    def parse_payment(params, profile="default"):
        return PaymentNotify(params, LsPay.get_config(profile))

    @staticmethod
    def parse_refund(params, profile="default"):
        return RefundNotify(params, LsPay.get_config(profile))

    @staticmethod
    def parse_delay_trans_transfer(params, profile="default"):
        return DelayTransTransferNotify(params, LsPay.get_config(profile))

    @staticmethod
    def success_response(): return "ok"

    @staticmethod
    def failure_response(): return "fail"


def _event_prefix(notify: BaseNotify, prefix: str) -> None:
    for value in (notify.get_notify_type(), notify.get_event_type()):
        if value and not value.startswith(prefix):
            raise LsPayValidationException("通知类型不匹配")


__all__ = [
    "BaseNotify", "PaymentNotify", "RefundNotify",
    "DelayTransTransferNotify", "LsPayNotify",
]
