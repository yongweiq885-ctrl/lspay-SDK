"""Typed responses for every supported endpoint."""

import json
from typing import Any, Dict, List, Optional

from ..core.ls_pay_response import LsPayResponse
from ..enums.business import OrderState, RefundState


class DataResponse(LsPayResponse):
    def _str(self, key: str) -> Optional[str]:
        value = self.business_value(key)
        return None if value is None else str(value)

    def _int(self, key: str) -> Optional[int]:
        value = self.business_value(key)
        return None if value in (None, "") else int(value)


class UnifiedOrderResponse(DataResponse):
    def get_pay_order_id(self): return self._str("pay_order_id")
    def get_mch_order_no(self): return self._str("mch_order_no")
    def get_amount(self): return self._int("amount")
    def get_order_state(self): return self._int("order_state")
    def get_pay_info(self): return self.business_value("pay_info")
    def get_trans_status(self): return self._str("trans_status")
    def get_err_code(self): return self._str("err_code")
    def get_err_msg(self): return self._str("err_msg")
    def is_accepted(self): return not self.is_error() and self.get_trans_status() == "S"
    def is_paid(self): return self.get_order_state() == OrderState.PAID


class BalancePayResponse(UnifiedOrderResponse):
    pass


class QuickPayApplyResponse(UnifiedOrderResponse):
    def is_accepted(self):
        return not self.is_error() and self.get_trans_status() in ("S", "P")
    def is_processing(self): return not self.is_error() and self.get_trans_status() == "P"


class QueryOrderResponse(DataResponse):
    def get_pay_order_id(self): return self._str("pay_order_id")
    def get_mch_no(self): return self._str("mch_no")
    def get_app_id(self): return self._str("app_id") or self._str("app_Id")
    def get_mch_order_no(self): return self._str("mch_order_no")
    def get_way_code(self): return self._str("way_code")
    def get_currency(self): return self._str("currency")
    def get_client_ip(self): return self._str("client_ip")
    def get_subject(self): return self._str("subject")
    def get_body(self): return self._str("body")
    def get_channel_order_no(self): return self._str("channel_order_no")
    def get_err_code(self): return self._str("err_code")
    def get_err_msg(self): return self._str("err_msg")
    def get_ext_param(self): return self.business_value("ext_param")
    def get_amount(self): return self._int("amount")
    def get_state(self): return self._int("state")
    def get_order_state(self): return self.get_state()
    def get_add_time(self): return self._int("add_time")  # 10 位秒级时间戳
    def get_success_time(self): return self._int("success_time")  # 10 位秒级时间戳
    def get_division_mode(self): return self._int("division_mode")
    def get_division_state(self): return self._int("division_state")
    def get_unconfirm_amount(self): return self._int("unconfirm_amount")
    def get_confirmed_amount(self): return self._int("confirmed_amount")
    def is_paid(self): return not self.is_error() and self.get_state() == OrderState.PAID


class RefundResponse(DataResponse):
    def get_refund_order_id(self):
        return self._str("refund_order_id") or self._str("order_refund_id") or self._str("order_refund_Id")
    def get_order_refund_id(self): return self.get_refund_order_id()
    def get_mch_refund_no(self): return self._str("mch_refund_no")
    def get_channel_order_no(self): return self._str("channel_order_no") or self._str("channel_rrder_no")
    def get_err_code(self): return self._str("err_code")
    def get_err_msg(self): return self._str("err_msg")
    def get_state(self): return self._int("state")
    def get_pay_amount(self): return self._int("pay_amount")
    def get_refund_amount(self): return self._int("refund_amount")
    def is_accepted(self): return not self.is_error()
    def is_refunded(self): return not self.is_error() and self.get_state() == RefundState.SUCCEEDED


class QueryRefundResponse(RefundResponse):
    def get_pay_order_id(self): return self._str("pay_order_id")
    def get_mch_no(self): return self._str("mch_no")
    def get_app_id(self): return self._str("app_id")
    def get_currency(self): return self._str("currency")
    def get_ext_param(self): return self.business_value("ext_param")
    def get_add_time(self): return self._int("add_time")  # 10 位秒级时间戳
    def get_success_time(self): return self._int("success_time")  # 10 位秒级时间戳


class DelayTransConfirmResponse(DataResponse):
    def get_unconfirm_amount(self): return self._int("unconfirm_amount")
    def get_confirmed_amount(self): return self._int("confirmed_amount")
    def get_confirm_order_id(self): return self._str("confirm_order_id")
    def get_batch_order_id(self): return self._str("batch_order_id")
    def get_trans_status(self): return self._str("trans_status")
    def get_err_code(self): return self._str("err_code")
    def get_err_msg(self): return self._str("err_msg")
    def is_accepted(self): return not self.is_error()
    def is_successful(self): return not self.is_error() and self.get_trans_status() == "S"


class DelayTransConfirmRefundResponse(DelayTransConfirmResponse):
    def get_confirm_refund_no(self): return self._str("confirm_refund_no")


class DelayTransUnconfirmQueryResponse(DataResponse):
    def get_unconfirm_amount(self): return self._int("unconfirm_amount")


class DelayTransCreateTaskResponse(DataResponse):
    def get_task_id(self): return self._str("task_id")
    def get_order_no(self): return self._str("order_no")
    def get_account_no(self): return self._str("account_no")
    def get_account_name(self): return self._str("account_name")
    def get_amount(self): return self._int("amount")
    def get_task_status(self): return self._str("status")
    def get_selected_orders(self): return self._int("selected_orders")
    def get_first_order_time(self): return self._str("first_order_time")
    def get_last_order_time(self): return self._str("last_order_time")
    def is_created(self): return not self.is_error() and self.get_task_status() == "PENDING"


class QuickPayResponse(DataResponse):
    def get_trans_status(self): return self._str("trans_status")

    def is_error(self):
        raw = self.get_map()
        if any(key in raw for key in ("status", "code", "retCode")):
            return super().is_error()
        return self.get_trans_status() in (None, "F")

    def is_accepted(self): return not self.is_error()
    def is_successful(self): return not self.is_error() and self.get_trans_status() == "S"
    def is_processing(self): return not self.is_error() and self.get_trans_status() == "P"


class QuickPayBindApplyResponse(QuickPayResponse):
    def get_trans_id(self): return self._str("trans_id")
    def get_order_id(self): return self._str("order_id")
    def get_resp_desc(self): return self._str("resp_desc")


class QuickPayBindConfirmResponse(QuickPayResponse):
    def get_channel_user(self): return self._str("channel_user")
    def get_channel_card_id(self): return self._str("channel_card_id")
    def get_resp_desc(self): return self._str("resp_desc")


class QuickPayConfirmResponse(QuickPayResponse):
    def get_err_msg(self): return self._str("err_msg")


class QuickPaySmsCheckResponse(QuickPayConfirmResponse):
    pass


class QuickPayUnbindResponse(QuickPayResponse):
    def get_order_id(self): return self._str("order_id")
    def get_resp_desc(self): return self._str("resp_desc")


class ElectronicAccountResponse(QuickPayResponse):
    pass


class ElectronicAccountUserOpenResponse(ElectronicAccountResponse):
    def get_account_no(self): return self._str("account_no")
    def get_resp_desc(self): return self._str("resp_desc")


class ElectronicAccountBusinessOpenResponse(ElectronicAccountUserOpenResponse):
    pass


class ElectronicAccountBusinessChangeResponse(ElectronicAccountUserOpenResponse):
    pass


class ElectronicAccountUserQueryResponse(DataResponse):
    def get_account_no(self): return self._str("account_no")
    def get_account_name(self): return self._str("account_name")
    def get_account_type(self): return self._str("account_type")
    def get_relation_type(self): return self._str("relation_type")
    def get_relation_type_name(self): return self._str("relation_type_name")
    def get_division_profit(self): return self._str("division_profit")
    def get_trans_status(self): return self._str("trans_status")
    def get_channel_status(self): return self._str("channel_status")
    def get_resp_desc(self): return self._str("resp_desc")
    def get_channel_extra(self): return self.business_value("channel_extra")
    def is_onboarded(self):
        """查询资料中的 channel_status 是否为 1。开户成功看开户接口的 trans_status。"""
        return (not self.is_error() and self.get_trans_status() == "S"
                and self.get_channel_status() == "1")

    def get_channel_extra_dict(self) -> Dict[str, Any]:
        value = self.get_channel_extra()
        if isinstance(value, dict):
            return value
        try:
            parsed = json.loads(value or "")
            return parsed if isinstance(parsed, dict) else {}
        except (TypeError, ValueError):
            return {}


class ElectronicAccountSettlementQueryResponse(DataResponse):
    def get_records(self) -> List[Dict[str, Any]]:
        raw_data = self.get_map().get("data")
        if isinstance(raw_data, str):
            try:
                raw_data = json.loads(raw_data)
            except (TypeError, ValueError):
                raw_data = None
        if isinstance(raw_data, list):
            return [item for item in raw_data if isinstance(item, dict)]
        data = self.get_data()
        for key in ("records", "list", "rows"):
            value = data.get(key)
            if isinstance(value, list):
                return value
        return [data] if "trans_id" in data else []

    def get_first_record(self):
        return self.get_records()[0] if self.get_records() else {}

    def _record(self, key):
        value = self.get_first_record().get(key)
        return None if value is None else str(value)

    def get_trans_id(self): return self._record("trans_id")
    def get_trans_date(self): return self._record("trans_date")
    def get_settle_cycle(self): return self._record("settle_cycle")
    def get_card_no(self): return self._record("card_no")
    def get_card_name(self): return self._record("card_name")
    def get_bank_code(self): return self._record("bank_code")
    def get_trans_amount(self): return self._record("trans_amt")
    def get_fee_amount(self): return self._record("fee_amt")
    def get_trans_status(self): return self._record("trans_stat")
    def get_settle_type(self): return self._record("settle_type")
    def get_settle_abstract(self): return self._record("settle_abstract")
    def get_settle_batch_no(self): return self._record("settle_batch_no")
    def get_settle_description(self): return self._record("settle_desc")


class ElectronicAccountBalanceWithdrawResponse(DataResponse):
    def get_acceptance_message(self): return self._str("err_msg")
    def get_acceptance_status(self): return self._str("status")
    def is_accepted(self):
        return (
            not self.is_error()
            and str(self.get_acceptance_status()).lower() in ("success", "succeess")
        )


class ElectronicAccountSingleWithdrawQueryResponse(DataResponse):
    def get_withdraw_date(self): return self._str("withdraw_date")
    def get_order_id(self): return self._str("order_id")
    def get_cash_amount(self): return self._str("cash_amt")
    def get_fee_amount(self): return self._str("fee_amt")
    def get_trans_status(self): return self._str("trans_status")
    def get_trans_description(self): return self._str("trans_desc")
    def get_channel_status(self): return self._str("status")
    def get_channel_message(self): return self._str("err_msg")
    def is_channel_successful(self):
        return str(self.get_channel_status() or "").lower() in ("success", "succeess")


class ElectronicAccountBatchWithdrawQueryResponse(ElectronicAccountSettlementQueryResponse):
    def get_records(self):
        value = self.get_data().get("data", [])
        return value if isinstance(value, list) else []
    def get_count(self): return self._int("count")
    def get_page_size(self): return self._int("page_size")
    def get_page_num(self): return self._int("page_num")
    def get_channel_status(self): return self._str("status")
    def is_channel_successful(self):
        return str(self.get_channel_status() or "").lower() in ("success", "succeess")
    def get_settle_date(self): return self._record("settle_date")
    def get_bank_name(self): return self._record("bank_name")
    def get_batch_trans_type(self): return self._record("batch_trans_type")
    def get_cash_amount(self): return self._record("cash_amt")
    def get_fee_flag(self): return self._record("fee_flag")
    def get_original_request_date(self): return self._record("org_req_date")
    def get_order_id(self): return self._record("order_id")
    def get_trans_status(self): return self._record("trans_status")
    def get_remark(self): return self._record("remark")
    def get_trans_description(self): return self._record("trans_desc")
    def is_first_record_pending(self): return not self.is_error() and self.get_trans_status() == "pending"
    def is_first_record_failed(self): return not self.is_error() and self.get_trans_status() == "failed"
    def is_first_record_succeeded(self): return not self.is_error() and self.get_trans_status() == "succeeded"


class BalanceQueryResponse(DataResponse):
    def _balance(self):
        data = self.get_data()
        if data:
            return data
        raw = self.get_map()
        return raw if "acct_type" in raw else {}

    def is_error(self):
        raw = self.get_map()
        if "status" not in raw and "acct_type" in raw:
            return False
        return super().is_error()

    def get_account_type(self): return self._balance().get("acct_type")
    def get_account_status(self): return self._balance().get("acct_stat")
    def get_balance_amount(self): return self._balance().get("balance_amt")
    def get_available_balance(self): return self._balance().get("avl_balance")
    def get_frozen_balance(self): return self._balance().get("frz_bal")
    def get_historical_balance(self): return self._balance().get("dm_amt")
    def is_normal(self): return not self.is_error() and self.get_account_status() == "N"


__all__ = [name for name in list(globals()) if name.endswith("Response")]
