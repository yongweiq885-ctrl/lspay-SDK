"""BaseRequest - 所有 Request 的抽象基类"""

from dataclasses import dataclass, field, fields
from typing import Any, Dict, Type

from ..exceptions import LsPayValidationException
from ..validation import json_object

@dataclass
class BaseRequest:
    """
    子类用 @dataclass 声明字段；字段命名必须保持下划线风格（与 API 一致）。
    extend_infos 透传到顶层 body。
    """

    extend_infos: Dict[str, Any] = field(default_factory=dict)

    def get_function_code(self) -> str:
        raise NotImplementedError("子类必须实现 get_function_code()")

    def get_response_class(self) -> Type:
        from ..core.ls_pay_response import LsPayResponse
        return LsPayResponse

    def validate(self) -> None:
        """Validate the request before signing and transport."""

    def set_extend_info(self, values: Dict[str, Any]):
        self.extend_infos = dict(values)
        return self

    def to_map(self) -> Dict[str, Any]:
        """
        把所有非 extend_infos 字段拼成 dict，跳过 None 与空字符串。
        最后再把 extend_infos 合并进来。
        """
        out: Dict[str, Any] = {}
        for f in fields(self):
            if f.name == "extend_infos":
                continue
            v = getattr(self, f.name)
            if v is None:
                continue
            if isinstance(v, str) and v == "":
                continue
            if f.name == "channel_extra":
                v = json_object(v, "channel_extra")
            elif hasattr(v, "to_json"):
                v = v.to_json()
            out[f.name] = v
        if self.extend_infos:
            duplicates = set(self.extend_infos).intersection(out)
            if duplicates:
                raise LsPayValidationException(
                    "扩展参数不允许覆盖请求字段: %s"
                    % ", ".join(sorted(duplicates))
                )
            for k, v in self.extend_infos.items():
                if v is None:
                    continue
                out[k] = v
        return out
