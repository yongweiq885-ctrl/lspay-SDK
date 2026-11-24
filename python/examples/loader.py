"""Demo 通用初始化"""

import os
import sys

# 允许从仓库根目录直接执行
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from lspay import LsPay, MerConfig  # noqa: E402


_inited = False


def _env(key: str) -> str:
    return os.environ.get(key, "").strip()


def _load_config(prefix: str) -> MerConfig:
    missing = [
        prefix + name
        for name in ("MCH_NO", "APP_ID", "MD5_KEY")
        if not _env(prefix + name)
    ]
    if missing:
        raise RuntimeError("未配置商户信息，请先配置环境变量 " + "、".join(missing))
    return MerConfig(
        mch_no=_env(prefix + "MCH_NO"),
        app_id=_env(prefix + "APP_ID"),
        md5_key=_env(prefix + "MD5_KEY"),
        notify_url=_env(prefix + "NOTIFY_URL"),
        return_url=_env(prefix + "RETURN_URL"),
    )


def init() -> None:
    global _inited
    if _inited:
        return

    LsPay.DEBUG     = True
    LsPay.PROD_MODE = True
    LsPay.GATEWAY   = "https://api.lspay.tech/"

    LsPay.init("default", _load_config("LSPAY_"))
    if any(_env("LSPAY_B_" + name) for name in ("MCH_NO", "APP_ID", "MD5_KEY")):
        LsPay.init("merch_b", _load_config("LSPAY_B_"))
    _inited = True
