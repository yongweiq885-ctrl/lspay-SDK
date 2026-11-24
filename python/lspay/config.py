"""商户配置对象"""

from dataclasses import dataclass


@dataclass
class MerConfig:
    """商户配置"""

    mch_no: str = ""
    app_id: str = ""
    md5_key: str = ""
    notify_url: str = ""
    return_url: str = ""
