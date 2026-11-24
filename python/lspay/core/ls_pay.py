"""LsPay - SDK 总控（多商户 + HTTP）"""

import json
import ssl
import urllib.error
import urllib.parse
import urllib.request
from typing import Callable, Dict, Mapping, Optional, Type

from ..config import MerConfig
from ..enums.endpoint import Endpoint
from ..exceptions import (
    LsPayConfigException,
    LsPayNetworkException,
    LsPayProtocolException,
    LsPayValidationException,
)
from .ls_pay_response import LsPayResponse
from .ls_pay_tools import LsPayTools
from ..validation import http_url


class LsPay:
    """商户配置注册与签名 POST 调用。"""

    # 全局
    GATEWAY: str = "https://api.lspay.tech/"
    # API 接口版本号 / 签名类型（写入每次请求的 version / sign_type 字段）
    API_VERSION: str = "1.0"
    SIGN_TYPE: str = "MD5"

    CONNECT_MS: int = 10_000
    READ_MS: int = 15_000
    DEBUG: bool = False
    PROD_MODE: bool = True
    INSECURE: bool = False
    LOGGER: Optional[Callable[[str], None]] = None

    # 多商户
    _configs: Dict[str, MerConfig] = {}

    # ----------- 注册 -----------
    @classmethod
    def init(cls, profile_or_cfg, cfg: Optional[MerConfig] = None) -> None:
        """LsPay.init(cfg) 或 LsPay.init(profile, cfg)。"""
        if isinstance(profile_or_cfg, MerConfig):
            profile = "default"
            real_cfg: MerConfig = profile_or_cfg
        else:
            profile = profile_or_cfg
            if cfg is None:
                raise LsPayConfigException("第二个参数 MerConfig 必填")
            real_cfg = cfg
        cls._validate(real_cfg)
        cls._configs[profile] = real_cfg

    @classmethod
    def get_config(cls, profile: str = "default") -> MerConfig:
        cfg = cls._configs.get(profile)
        if cfg is None:
            raise LsPayConfigException(f"未注册商户配置: {profile}")
        return cfg

    @classmethod
    def has_config(cls, profile: str) -> bool:
        return profile in cls._configs

    @classmethod
    def clear(cls) -> None:
        cls._configs.clear()

    @staticmethod
    def _validate(cfg: MerConfig) -> None:
        if not cfg.mch_no:
            raise LsPayConfigException("mch_no 必填")
        if not cfg.app_id:
            raise LsPayConfigException("app_id 必填")
        if not cfg.md5_key:
            raise LsPayConfigException("md5_key 必填")
        if len(str(cfg.mch_no)) > 30:
            raise LsPayConfigException("mch_no 长度不能超过 30")
        if len(str(cfg.app_id)) > 24:
            raise LsPayConfigException("app_id 长度不能超过 24")
        try:
            http_url("notify_url", cfg.notify_url)
            http_url("return_url", cfg.return_url)
        except LsPayValidationException as exc:
            raise LsPayConfigException(str(exc)) from exc

    # ----------- HTTP -----------
    @classmethod
    def post(
        cls,
        path: str,
        biz_params: Mapping,
        profile: str = "default",
        response_class: Type[LsPayResponse] = LsPayResponse,
    ) -> LsPayResponse:
        """
        发起一次签名 + POST 调用

        公共字段: mch_no / app_id / req_time / version / sign_type / sign
        传输格式: application/x-www-form-urlencoded;charset=utf-8
        """
        if path == Endpoint.UNIFIED_ORDER and cls.is_cashier_order((biz_params or {}).get("channel_extra")):
            raise LsPayValidationException(
                "pay_data_type=cashier 时网关返回收银台页面，不能由服务端调用接口；"
                "请使用 LsPayClient.build_cashier_form() 生成表单，由用户浏览器提交"
            )
        all_params = cls.build_signed_params(path, biz_params, profile)

        url = cls._build_url(path)
        body = urllib.parse.urlencode(all_params).encode("utf-8")
        cls._log("REQUEST %s %s" % (
            url, json.dumps(cls.sanitize_for_log(all_params), ensure_ascii=False)
        ))
        return cls._send(url, body, response_class)

    @staticmethod
    def is_cashier_order(channel_extra) -> bool:
        """channel_extra.pay_data_type 是否为 cashier。"""
        if channel_extra in (None, ""):
            return False
        try:
            extra = channel_extra if isinstance(channel_extra, Mapping) else json.loads(str(channel_extra))
        except (TypeError, ValueError):
            return False
        return isinstance(extra, Mapping) and extra.get("pay_data_type") == "cashier"

    @classmethod
    def build_signed_params(cls, path: str, biz_params: Mapping, profile: str = "default") -> Dict:
        """注入公共字段并签名，返回可直接提交的参数。"""
        cfg = cls.get_config(profile)

        reserved = {"mch_no", "app_id", "req_time", "version", "sign_type", "sign"}
        overlap = reserved.intersection(biz_params or {})
        if overlap:
            raise LsPayValidationException(
                "%s 是 SDK 管理的公共参数，业务参数不允许覆盖"
                % sorted(overlap)[0]
            )
        params = {
            k: v for k, v in (biz_params or {}).items()
            if v is not None and v != ""
        }
        if path == Endpoint.UNIFIED_ORDER:
            if "notify_url" not in params and cfg.notify_url:
                params["notify_url"] = cfg.notify_url
            if "return_url" not in params and cfg.return_url:
                params["return_url"] = cfg.return_url

        all_params: Dict = {
            "mch_no":    cfg.mch_no,
            "app_id":    cfg.app_id,
            "req_time":  LsPayTools.msec_time(),
            "version":   cls.API_VERSION,
            "sign_type": cls.SIGN_TYPE,
        }
        all_params.update(params)

        all_params["sign"] = LsPayTools.sign(all_params, cfg.md5_key)
        return all_params

    @classmethod
    def _send(cls, url: str, body: bytes, response_class: Type[LsPayResponse]) -> LsPayResponse:
        req = urllib.request.Request(
            url,
            data=body,
            headers={
                "Content-Type": "application/x-www-form-urlencoded;charset=utf-8",
                "Accept": "application/json",
            },
            method="POST",
        )
        ctx = None
        if url.startswith("https://") and cls.INSECURE:
            ctx = ssl.create_default_context()
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE
        try:
            timeout = max(cls.CONNECT_MS, cls.READ_MS) / 1000.0
            with urllib.request.urlopen(req, timeout=timeout, context=ctx) as resp:
                raw = resp.read().decode("utf-8")
        except urllib.error.HTTPError as e:
            try:
                err_body = e.read().decode("utf-8")
            except Exception:
                err_body = ""
            detail = cls._safe_error_body(err_body)
            raise LsPayNetworkException("HTTP %s body=%s" % (e.code, detail)) from e
        except Exception as e:
            raise LsPayNetworkException(f"HTTP 请求失败: {e}") from e

        try:
            decoded = json.loads(raw)
        except (TypeError, ValueError) as exc:
            raise LsPayProtocolException("立收通响应不是合法 JSON 对象") from exc
        if not isinstance(decoded, dict):
            raise LsPayProtocolException("立收通响应不是合法 JSON 对象")
        cls._log("RESPONSE %s %s" % (
            url, json.dumps(cls.sanitize_for_log(decoded), ensure_ascii=False)
        ))
        if not isinstance(response_class, type) or not issubclass(response_class, LsPayResponse):
            raise LsPayConfigException("非法响应类")
        return response_class(decoded)

    # ----------- 内部 -----------
    @staticmethod
    def _build_url(path: str) -> str:
        base = LsPay.GATEWAY if LsPay.GATEWAY.endswith("/") else LsPay.GATEWAY + "/"
        tail = path.lstrip("/")
        return base + tail

    @classmethod
    def _log(cls, line: str) -> None:
        if not cls.DEBUG and cls.LOGGER is None:
            return
        if cls.LOGGER:
            cls.LOGGER(line)
        if cls.DEBUG:
            print(f"[LsPay] {line}")

    @classmethod
    def sanitize_for_log(cls, value):
        sensitive = {
            "sign", "md5_key", "auth_code", "openid", "buyer_id", "trans_in",
            "trans_out", "account_no", "account_name", "account_alias",
            "mch_user_id", "card_id", "card_name", "card_no", "cert_id",
            "cert_begin_date", "cert_end_date", "card_mp", "vip_code",
            "expiration", "verify_code", "channel_user", "channel_card_id",
            "name", "cert_no", "mobile_no", "email", "reg_name", "short_name",
            "license_code", "reg_detail", "legal_name", "legal_cert_no",
            "contact_name", "contact_mobile", "mp", "pay_info",
        }
        if isinstance(value, Mapping):
            result = {}
            for key, item in value.items():
                if key in sensitive:
                    result[key] = cls._mask(item)
                elif key == "channel_extra" and isinstance(item, str):
                    try:
                        result[key] = json.dumps(
                            cls.sanitize_for_log(json.loads(item)),
                            ensure_ascii=False, separators=(",", ":"),
                        )
                    except (TypeError, ValueError):
                        result[key] = "[invalid-json]"
                else:
                    result[key] = cls.sanitize_for_log(item)
            return result
        if isinstance(value, list):
            return [cls.sanitize_for_log(item) for item in value]
        return value

    @staticmethod
    def _mask(value) -> str:
        text = str(value)
        return "****" if len(text) <= 4 else text[:2] + "****" + text[-2:]

    @classmethod
    def _safe_error_body(cls, raw: str) -> str:
        try:
            value = json.loads(raw)
            if isinstance(value, dict):
                return json.dumps(cls.sanitize_for_log(value), ensure_ascii=False)
        except (TypeError, ValueError):
            pass
        return "[响应正文已省略]"
