"""LsPayResponse - 响应封装"""

import json
from typing import Any, Mapping, Union


class LsPayResponse:
    """网关响应，data 兼容 JSON 对象和 JSON 字符串。"""

    def __init__(self, raw: Union[str, Mapping[str, Any]]):
        if isinstance(raw, Mapping):
            obj = dict(raw)
            self.raw = json.dumps(obj, ensure_ascii=False, separators=(",", ":"))
        else:
            self.raw = raw or ""
            try:
                obj = json.loads(self.raw) if self.raw else {}
            except Exception:
                obj = {}
        self._data: Mapping[str, Any] = obj if isinstance(obj, dict) else {}

    def get_raw(self) -> str:
        return self.raw

    def get_map(self) -> Mapping[str, Any]:
        return self._data

    def get_status(self) -> str:
        value = self._data.get(
            "status",
            self._data.get("code", self._data.get("retCode", "")),
        )
        return "" if value is None else str(value)

    def get_code(self) -> str:
        """兼容旧代码，请优先使用 get_status()。"""
        return self.get_status()

    def get_msg(self) -> str:
        value = self._data.get("msg", self._data.get("retMsg", ""))
        return "" if value is None else str(value)

    def get_sign(self) -> str:
        value = self._data.get("sign", "")
        return "" if value is None else str(value)

    def is_error(self) -> bool:
        return self.get_status().upper() not in {"200", "0", "SUCCESS"}

    def get_error_info(self) -> str:
        return f"[{self.get_status()}] {self.get_msg()}"

    def get_data(self) -> Mapping[str, Any]:
        d = self._data.get("data")
        if isinstance(d, str) and d:
            try:
                d = json.loads(d)
            except Exception:
                d = None
        return d if isinstance(d, dict) else {}

    def data_value(self, key: str, default=None):
        return self.get_data().get(key, default)

    def business_value(self, key: str, default=None):
        data = self.get_data()
        if key in data:
            return data[key]
        return self._data.get(key, default)
