"""Shared protocol validation and JSON helpers."""

import datetime
import ipaddress
import json
import re
from typing import Any, Dict, Iterable, Optional
from urllib.parse import urlparse

from .exceptions import LsPayValidationException


def fail(message: str) -> None:
    raise LsPayValidationException(message)


def required_string(field: str, value: Any, max_length: int) -> str:
    if value is None or isinstance(value, (dict, list, tuple, set)):
        fail("%s 必填且必须是字符串" % field)
    text = str(value)
    if not text.strip():
        fail("%s 必填" % field)
    if len(text) > max_length:
        fail("%s 长度不能超过 %s" % (field, max_length))
    return text


def optional_string(field: str, value: Any, max_length: int) -> Optional[str]:
    if value is None or value == "":
        return None
    if isinstance(value, (dict, list, tuple, set)):
        fail("%s 必须是字符串" % field)
    text = str(value)
    if len(text) > max_length:
        fail("%s 长度不能超过 %s" % (field, max_length))
    return text


def positive_int(field: str, value: Any, optional: bool = False) -> Optional[int]:
    if optional and (value is None or value == ""):
        return None
    if isinstance(value, bool) or not re.match(r"^[+-]?\d+$", str(value or "")):
        fail("%s 必须是正整数" % field)
    number = int(value)
    if number <= 0:
        fail("%s 必须是正整数" % field)
    return number


def integer(field: str, value: Any) -> int:
    if isinstance(value, bool) or not re.match(r"^[+-]?\d+$", str(value or "")):
        fail("%s 必须是整数" % field)
    return int(value)


def ipv4(field: str, value: Any) -> None:
    if value is None or value == "":
        return
    try:
        if ipaddress.ip_address(str(value)).version != 4:
            fail("%s 必须是合法 IPv4 地址" % field)
    except ValueError:
        fail("%s 必须是合法 IPv4 地址" % field)


def http_url(field: str, value: Any, max_length: int = 128) -> None:
    if value is None or value == "":
        return
    text = optional_string(field, value, max_length)
    parsed = urlparse(text or "")
    if parsed.scheme.lower() not in ("http", "https") or not parsed.netloc:
        fail("%s 必须是合法 HTTP(S) URL" % field)


def date_ymd(field: str, value: Any) -> datetime.date:
    text = str(value or "")
    if not re.match(r"^\d{8}$", text):
        fail("%s 必须是合法的 yyyyMMdd 格式日期" % field)
    try:
        return datetime.datetime.strptime(text, "%Y%m%d").date()
    except ValueError:
        fail("%s 必须是合法的 yyyyMMdd 格式日期" % field)
    raise AssertionError("unreachable")


def datetime_value(field: str, value: Any) -> datetime.datetime:
    text = str(value or "")
    try:
        parsed = datetime.datetime.strptime(text, "%Y-%m-%d %H:%M:%S")
    except ValueError:
        fail("%s 必须是 yyyy-MM-dd HH:mm:ss 格式" % field)
    if parsed.strftime("%Y-%m-%d %H:%M:%S") != text:
        fail("%s 必须是 yyyy-MM-dd HH:mm:ss 格式" % field)
    return parsed


def json_object(value: Any, field: str = "channel_extra") -> Optional[str]:
    if value is None or value == "":
        return None
    if hasattr(value, "to_dict"):
        value = value.to_dict()
    elif hasattr(value, "to_array"):
        value = value.to_array()
    if isinstance(value, str):
        try:
            value = json.loads(value)
        except (TypeError, ValueError):
            fail("%s 必须是合法 JSON 对象字符串" % field)
    if not isinstance(value, dict):
        fail("%s 必须是 JSON 对象，不能是数组列表" % field)
    try:
        return json.dumps(value, ensure_ascii=False, separators=(",", ":"))
    except (TypeError, ValueError) as exc:
        fail("%s JSON 编码失败: %s" % (field, exc))
    return None


def json_dict(value: Any, field: str = "channel_extra") -> Dict[str, Any]:
    encoded = json_object(value, field)
    if encoded is None:
        return {}
    return json.loads(encoded)


def one_of(field: str, value: Any, allowed: Iterable[Any]) -> None:
    if value not in allowed:
        fail("%s 非法" % field)


def validate_division_list(value: Any, required: bool = False) -> None:
    if not isinstance(value, list) or (required and not value):
        fail("channel_extra.div_list 必须是%s数组列表" % ("非空" if required else ""))
    for index, item in enumerate(value):
        prefix = "channel_extra.div_list[%d]" % index
        if not isinstance(item, dict):
            fail("%s 必须是 JSON 对象" % prefix)
        required_string(prefix + ".trans_in", item.get("trans_in"), 256)
        if "div_amt" in item:
            amount = item["div_amt"]
            if isinstance(amount, bool) or not isinstance(amount, int) or amount <= 0:
                fail("%s.div_amt 必须是大于 0 的整数（单位：分）" % prefix)
        if "description" in item and not isinstance(item["description"], str):
            fail("%s.description 必须是字符串" % prefix)
