"""个人电子账户开通请求构造示例。"""

import loader  # noqa: F401

from lspay import ElectronicAccountUserOpenRequest


request = ElectronicAccountUserOpenRequest(
    order_id="USER_OPEN_001",
    account_name="张三",
    account_type=1,
    relation_type="USER",
    account_alias="示例用户",
    division_profit="0.1",
    channel_extra={
        "user_info": {
            "name": "张三",
            "cert_no": "110101199001010000",
            "cert_validity_type": "0",
            "cert_begin_date": "20200101",
            "cert_end_date": "20300101",
            "mobile_no": "13800000000",
            "email": "demo@example.com",
        }
    },
)

request.validate()
print(request.get_function_code(), request.to_map())
