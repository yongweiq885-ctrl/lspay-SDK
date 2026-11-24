"""Typed channel_extra builders."""

import json
import re
from typing import Any, Dict, Iterable, List, Optional

from ..enums.business import PayDataType
from ..exceptions import LsPayValidationException
from ..validation import date_ymd, fail, integer, required_string


class JsonBuilder:
    def to_dict(self) -> Dict[str, Any]:
        raise NotImplementedError

    def to_array(self) -> Dict[str, Any]:
        return self.to_dict()

    def to_json(self) -> str:
        return json.dumps(self.to_dict(), ensure_ascii=False, separators=(",", ":"))


class DivisionReceiver(JsonBuilder):
    def __init__(
        self, trans_in: Optional[str] = None, div_amt: Optional[int] = None,
        description: Optional[str] = None
    ):
        self.trans_in = trans_in
        self.div_amt = div_amt
        self.description = description

    def to_dict(self) -> Dict[str, Any]:
        trans_in = required_string("div_list.trans_in", self.trans_in, 256)
        data = {"trans_in": trans_in}
        if self.div_amt is not None:
            if isinstance(self.div_amt, bool) or not isinstance(self.div_amt, int) or self.div_amt <= 0:
                fail("div_list.div_amt 必须是大于 0 的整数（单位：分）")
            data["div_amt"] = self.div_amt
        if self.description is not None:
            if not isinstance(self.description, str):
                fail("div_list.description 必须是字符串")
            data["description"] = self.description
        return data


class DivisionChannelExtra(JsonBuilder):
    def __init__(self, **values: Any):
        self.values = dict(values)

    def set(self, key: str, value: Any):
        if not str(key).strip():
            fail("channel_extra 参数名不能为空")
        if value is None or value == "":
            self.values.pop(str(key), None)
        else:
            self.values[str(key)] = value
        return self

    def set_division_receivers(self, receivers: Iterable[Any]):
        self.values["div_list"] = [
            item.to_dict() if isinstance(item, DivisionReceiver) else dict(item)
            for item in receivers
        ]
        return self

    def add_division_receiver(self, receiver: DivisionReceiver):
        self.values.setdefault("div_list", []).append(receiver.to_dict())
        return self

    def to_dict(self) -> Dict[str, Any]:
        return dict(self.values)


class UnifiedOrderChannelExtra(DivisionChannelExtra):
    def set_buyer_id(self, value: Any): return self.set("buyer_id", value)
    def set_openid(self, value: Any): return self.set("openid", value)
    def set_auth_code(self, value: Any): return self.set("auth_code", value)
    def set_channel_user(self, value: Any): return self.set("channel_user", value)
    def set_channel_card_id(self, value: Any): return self.set("channel_card_id", value)

    def set_pay_data_type(self, value: str):
        if value not in PayDataType.all():
            fail("channel_extra.pay_data_type 非法: %s" % value)
        return self.set("pay_data_type", value)


class BalancePayChannelExtra(DivisionChannelExtra):
    def set_trans_out(self, value: Any):
        return self.set("trans_out", required_string("channel_extra.trans_out", value, 256))


class QuickPayBindChannelExtra(JsonBuilder):
    def __init__(self, **kwargs: Any):
        self.values = {"cert_type": "00"}
        self.values.update({k: v for k, v in kwargs.items() if v is not None})

    def to_dict(self) -> Dict[str, Any]:
        required = [
            "mch_user_id", "card_id", "card_name", "cert_id",
            "cert_validity_type", "cert_begin_date", "card_mp", "dc_type",
        ]
        for field in required:
            required_string("channel_extra." + field, self.values.get(field), 256)
        if self.values.get("cert_validity_type") not in ("0", "1"):
            fail("channel_extra.cert_validity_type 只允许 0 或 1")
        begin = date_ymd("channel_extra.cert_begin_date", self.values["cert_begin_date"])
        end_text = self.values.get("cert_end_date")
        if self.values["cert_validity_type"] == "0" and not end_text:
            fail("非长期有效证件必须填写 channel_extra.cert_end_date")
        if end_text:
            end = date_ymd("channel_extra.cert_end_date", end_text)
            if end < begin:
                fail("channel_extra.cert_end_date 不能早于 cert_begin_date")
        dc_type = str(self.values["dc_type"]).upper()
        if dc_type not in ("C", "D", "S", "U"):
            fail("channel_extra.dc_type 只允许 C、D、S、U")
        self.values["dc_type"] = dc_type
        expiration = self.values.get("expiration")
        if expiration and not re.match(r"^(0[1-9]|1[0-2])\d{2}$", str(expiration)):
            fail("channel_extra.expiration 必须是 MMYY 格式")
        return dict(self.values)


class QuickPayBindConfirmChannelExtra(JsonBuilder):
    def __init__(self, **kwargs: Any):
        self.values = {"cert_type": "00"}
        self.values.update({k: v for k, v in kwargs.items() if v is not None})

    def to_dict(self) -> Dict[str, Any]:
        for field in ("mch_user_id", "card_id", "card_name", "cert_id", "card_mp", "dc_type"):
            required_string("channel_extra." + field, self.values.get(field), 256)
        self.values["dc_type"] = str(self.values["dc_type"]).upper()
        if self.values["dc_type"] not in ("C", "D", "S", "U"):
            fail("channel_extra.dc_type 只允许 C、D、S、U")
        return dict(self.values)


class QuickPayUnbindChannelExtra(JsonBuilder):
    def __init__(self, mch_user_id: str = "", channel_card_id: str = ""):
        self.mch_user_id = mch_user_id
        self.channel_card_id = channel_card_id

    def to_dict(self) -> Dict[str, Any]:
        return {
            "mch_user_id": required_string("channel_extra.mch_user_id", self.mch_user_id, 256),
            "channel_card_id": required_string(
                "channel_extra.channel_card_id", self.channel_card_id, 256
            ),
        }


class ElectronicAccountUserInfo(JsonBuilder):
    def __init__(self, **kwargs: Any):
        self.values = {"cert_type": "00"}
        self.values.update({k: v for k, v in kwargs.items() if v is not None})

    def to_dict(self) -> Dict[str, Any]:
        for field in ("name", "cert_no", "cert_validity_type", "cert_begin_date", "mobile_no", "email"):
            required_string("channel_extra.user_info." + field, self.values.get(field), 256)
        _validity_dates(self.values, "cert", "channel_extra.user_info")
        if not re.match(r"^[^@\s]+@[^@\s]+\.[^@\s]+$", str(self.values["email"])):
            fail("channel_extra.user_info.email 必须是合法邮箱")
        return dict(self.values)


class ElectronicAccountMerchantInfo(JsonBuilder):
    REQUIRED = (
        "reg_name", "short_name", "license_code", "license_validity_type",
        "license_begin_date", "reg_prov_id", "reg_area_id", "reg_district_id",
        "reg_detail", "legal_name", "legal_cert_type", "legal_cert_no",
        "legal_cert_validity_type", "legal_cert_begin_date", "contact_name",
        "contact_mobile",
    )

    def __init__(self, **kwargs: Any):
        self.values = {k: v for k, v in kwargs.items() if v is not None}

    def to_dict(self) -> Dict[str, Any]:
        for field in self.REQUIRED:
            required_string("channel_extra.mer_info." + field, self.values.get(field), 256)
        _validity_dates(self.values, "license", "channel_extra.mer_info")
        _validity_dates(self.values, "legal_cert", "channel_extra.mer_info")
        for field in ("reg_prov_id", "reg_area_id", "reg_district_id"):
            if not re.match(r"^\d{6}$", str(self.values[field])):
                fail("channel_extra.mer_info.%s 必须是 6 位地区编码" % field)
        return dict(self.values)


class ElectronicAccountOpenChannelExtra(JsonBuilder):
    def __init__(
        self, user_info: Optional[ElectronicAccountUserInfo] = None,
        mer_info: Optional[ElectronicAccountMerchantInfo] = None
    ):
        self.user_info = user_info
        self.mer_info = mer_info

    def to_dict(self) -> Dict[str, Any]:
        if self.user_info is not None:
            return {"user_info": self.user_info.to_dict()}
        if self.mer_info is not None:
            return {"mer_info": self.mer_info.to_dict()}
        fail("channel_extra 必须设置 user_info 或 mer_info")
        return {}


class ElectronicAccountSettlementCard(JsonBuilder):
    def __init__(self, **kwargs: Any):
        self.values = {k: v for k, v in kwargs.items() if v is not None}

    def to_dict(self) -> Dict[str, Any]:
        for field in ("card_type", "card_name", "card_no", "prov_id", "area_id", "mp"):
            required_string("channel_extra.card_info." + field, self.values.get(field), 256)
        card_type = str(self.values["card_type"])
        if card_type not in ("0", "1", "2"):
            fail("channel_extra.card_info.card_type 只允许 0、1、2")
        for field in ("prov_id", "area_id"):
            if not re.match(r"^\d{6}$", str(self.values[field])):
                fail("channel_extra.card_info.%s 必须是 6 位地区编码" % field)
        if card_type == "0":
            required_string("channel_extra.card_info.bank_code", self.values.get("bank_code"), 256)
            if not self.values.get("branch_code") and not self.values.get("branch_name"):
                fail("对公结算卡的 branch_code 与 branch_name 至少填写一个")
        else:
            for field in ("cert_type", "cert_no", "cert_validity_type", "cert_begin_date"):
                required_string("channel_extra.card_info." + field, self.values.get(field), 256)
            _validity_dates(self.values, "cert", "channel_extra.card_info")
        return dict(self.values)


class ElectronicAccountModifiedCard(JsonBuilder):
    def __init__(self, card_type: str = "", card_name: str = "", card_no: str = ""):
        self.values = {"card_type": card_type, "card_name": card_name, "card_no": card_no}

    def to_dict(self) -> Dict[str, Any]:
        if str(self.values["card_type"]) not in ("0", "1", "2"):
            fail("channel_extra.card_info.card_type 只允许 0、1、2")
        required_string("channel_extra.card_info.card_name", self.values["card_name"], 256)
        required_string("channel_extra.card_info.card_no", self.values["card_no"], 256)
        return dict(self.values)


class ElectronicAccountSettleConfig(JsonBuilder):
    def __init__(self, settle_cycle: str = "", settle_status: Any = None):
        self.settle_cycle = settle_cycle
        self.settle_status = settle_status

    def to_dict(self) -> Dict[str, Any]:
        cycle = str(self.settle_cycle).upper()
        if cycle not in ("T1", "D1"):
            fail("channel_extra.settle_config.settle_cycle 只允许 T1 或 D1")
        return {"settle_cycle": cycle, "settle_status": integer("settle_status", self.settle_status)}


class ElectronicAccountCashConfig(JsonBuilder):
    def __init__(self, cash_type: str = "", switch_state: Any = None, out_fee_flag: Any = None):
        self.cash_type = cash_type
        self.switch_state = switch_state
        self.out_fee_flag = out_fee_flag

    def to_dict(self) -> Dict[str, Any]:
        cash_type = str(self.cash_type).upper()
        if cash_type not in ("D0", "D1", "DM"):
            fail("channel_extra.cash_config.cash_type 只允许 D0、D1、DM")
        switch = integer("switch_state", self.switch_state)
        fee = integer("out_fee_flag", self.out_fee_flag)
        if switch not in (1, 2) or fee not in (1, 2):
            fail("channel_extra.cash_config 状态只允许 1 或 2")
        return {"cash_type": cash_type, "switch_state": switch, "out_fee_flag": fee}


class ElectronicAccountBusinessChannelExtra(JsonBuilder):
    def __init__(self, card_info=None, settle_config=None, cash_config=None):
        self.card_info = card_info
        self.settle_config = settle_config
        self.cash_config = list(cash_config or [])

    def to_dict(self) -> Dict[str, Any]:
        if self.card_info is None or self.settle_config is None or not self.cash_config:
            fail("channel_extra.card_info、settle_config、cash_config 均必填")
        return {
            "card_info": _builder_dict(self.card_info),
            "settle_config": _builder_dict(self.settle_config),
            "cash_config": [_builder_dict(item) for item in self.cash_config],
        }


class ElectronicAccountBusinessChangeChannelExtra(ElectronicAccountBusinessChannelExtra):
    pass


def _builder_dict(value: Any) -> Dict[str, Any]:
    if isinstance(value, dict):
        return dict(value)
    if hasattr(value, "to_dict"):
        return value.to_dict()
    fail("channel_extra 嵌套项必须是对象")
    return {}


def _validity_dates(values: Dict[str, Any], prefix: str, path: str) -> None:
    kind = str(values.get(prefix + "_validity_type", ""))
    if kind not in ("0", "1"):
        fail("%s.%s_validity_type 只允许 0 或 1" % (path, prefix))
    begin_text = values.get(prefix + "_begin_date")
    begin = date_ymd(path + "." + prefix + "_begin_date", begin_text)
    end_text = values.get(prefix + "_end_date")
    if kind == "0" and not end_text:
        fail("非长期有效证照必须填写 %s.%s_end_date" % (path, prefix))
    if end_text:
        end = date_ymd(path + "." + prefix + "_end_date", end_text)
        if end < begin:
            fail("%s.%s_end_date 不能早于开始日期" % (path, prefix))
