"""LsPayTools - 工具集（签名/时间/金额）"""

import hashlib
import json
import time
from decimal import ROUND_HALF_UP, Decimal
from typing import Mapping, Union


class LsPayTools:
    """签名、验签、时间戳与金额换算。"""

    # =========== 签名 ===========
    @staticmethod
    def sign(data: Mapping, secret: str = "") -> str:
        """
        立收通官方签名算法（doc.lspay.tech）：
            1. 移除 sign 字段
            2. 过滤 None / 空字符串
            3. 按字段名 ASCII 升序
            4. 按 k=v&k=v 顺序拼接；dict/list/tuple 先用 json.dumps(ensure_ascii=False) 序列化
            5. 末尾追加 &key=应用密钥（md5_key）
            6. MD5 → 大写

        注意：不要用 urllib.parse.urlencode 拼接（会对 value 做 URL 编码，与平台不一致）。
        """
        filtered = {}
        for k, v in data.items():
            if k == "sign":
                continue
            if v is None:
                continue
            if isinstance(v, (dict, list, tuple)):
                v = json.dumps(v, ensure_ascii=False, separators=(",", ":"))
            sv = str(v)
            if sv == "":
                continue
            filtered[k] = sv

        items = sorted(filtered.items(), key=lambda x: x[0])
        buf = "".join(f"{k}={v}&" for k, v in items)
        buf += f"key={secret or ''}"
        return hashlib.md5(buf.encode("utf-8")).hexdigest().upper()

    @staticmethod
    def verify_sign(data: Mapping, secret: str = "") -> bool:
        if not data:
            return False
        got = data.get("sign")
        if not got:
            return False
        expect = LsPayTools.sign(data, secret)
        return expect.upper() == str(got).upper()

    # =========== 时间 ===========
    @staticmethod
    def msec_time() -> str:
        return str(int(time.time() * 1000))

    # =========== 金额 ===========
    @staticmethod
    def to_fen(yuan: Union[str, int, float, Decimal]) -> int:
        d = Decimal(str(yuan)) * 100
        return int(d.quantize(Decimal("1"), rounding=ROUND_HALF_UP))

    @staticmethod
    def to_yuan(fen: Union[str, int, float, Decimal]) -> str:
        d = Decimal(str(fen)) / 100
        return str(d.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP))
