"""pay_data_type=cashier 的收银台表单。"""

from dataclasses import dataclass, field
from html import escape
from typing import Dict


@dataclass(frozen=True)
class CashierForm:
    """由用户浏览器把 fields 以 POST 提交到 action。"""

    action: str
    fields: Dict[str, str] = field(default_factory=dict)
    method: str = "POST"

    def to_html(self) -> str:
        """自动提交到立收通收银台的 HTML 表单。"""
        inputs = "".join(
            '<input type="hidden" name="%s" value="%s">' % (escape(name, quote=True), escape(value, quote=True))
            for name, value in self.fields.items()
        )
        return (
            '<form id="lspayCashierForm" method="POST" action="%s">%s</form>'
            '<script>document.getElementById("lspayCashierForm").submit();</script>'
            % (escape(self.action, quote=True), inputs)
        )
