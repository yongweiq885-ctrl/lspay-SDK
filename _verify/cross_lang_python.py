"""用 fixtures/cross_lang.json 校验 Python 签名，stdout 输出各用例签名供其他语言比对。"""

import json
import os
import sys

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "python")))

from lspay import LsPayTools  # noqa: E402

fixture = os.path.join(os.path.dirname(__file__), "fixtures", "cross_lang.json")
with open(fixture, "r", encoding="utf-8") as f:
    fx = json.load(f)

secret = fx["secret"]
out = {"language": "python", "signs": {}}
fail = 0
for t in fx["tests"]:
    sig = LsPayTools.sign(t["data"], secret)
    out["signs"][t["name"]] = sig
    ok = sig == t["expect_sign"]
    sys.stderr.write(
        f"  [{'OK  ' if ok else 'FAIL'}] {t['name']:25s} sig={sig} expect={t['expect_sign']}\n"
    )
    if not ok:
        fail += 1

print(json.dumps(out, ensure_ascii=False, indent=2))
sys.exit(1 if fail else 0)
