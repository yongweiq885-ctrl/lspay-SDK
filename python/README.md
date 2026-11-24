# 立收通 (LSPay) Python SDK

Python 3.7+。覆盖支付、退款、余额支付/查询、延时分账、快捷支付、电子账户全部接口，以及支付、退款、延时分账转账通知的验签与类型化解析。接口清单见 `contracts/endpoints.json`。

```
python/
├─ lspay/
│   ├─ __init__.py            统一导出
│   ├─ config.py              MerConfig (dataclass)
│   ├─ exceptions.py          异常体系
│   ├─ enums/
│   │   ├─ way_code.py        WayCode
│   │   └─ endpoint.py        Endpoint
│   ├─ core/
│   │   ├─ ls_pay.py          多商户 + HTTP
│   │   ├─ ls_pay_client.py   Request -> HTTP
│   │   ├─ ls_pay_response.py 响应封装
│   │   └─ ls_pay_tools.py    签名 / 金额 / 时间
│   ├─ request/               全量请求、RequestFactory、channel_extra 构造器
│   ├─ response/              类型化响应
│   └─ notify/                支付/退款/转账通知验签
└─ examples/
    ├─ loader.py              Demo 通用初始化
    ├─ demo_constants.py      Demo 常量
    ├─ unified_order_request_demo.py
    ├─ query_order_request_demo.py
    ├─ refund_request_demo.py
    ├─ query_refund_request_demo.py
    ├─ notify_verify_demo.py
    ├─ balance_demo.py
    ├─ delay_trans_demo.py
    ├─ quick_pay_demo.py
    ├─ electronic_account_demo.py
    └─ scenarios/
        ├─ a_qr_demo.py        ALI_QR
        ├─ a_lite_h5_demo.py   ALI_LITE_H5
        ├─ w_pub_demo.py       WX_PUB (微信 JSAPI)
        ├─ w_lite_demo.py      WX_LITE (微信小程序)
        ├─ w_lite_h5_demo.py   WX_LITE_H5 (微信 App / H5 跳转收银台)
        ├─ w_lite_jump_demo.py WX_LITE_JUMP (自有小程序跳收银台)
        └─ cup_qr_demo.py      CUP_QR
```

## 1. 安装

```bash
cd python
pip install -e .          # 可选；不安装也可直接 PYTHONPATH=. 跑 Demo
```

## 2. 最简用法

```python
from lspay import LsPay, LsPayClient, MerConfig, WayCode, UnifiedOrderRequest

LsPay.init("default", MerConfig(
    mch_no="...", app_id="...", md5_key="...",
    notify_url="https://your.domain/notify",
))

req = UnifiedOrderRequest(
    mch_order_no="JT1700000000000",
    way_code=WayCode.ALI_QR,
    amount=1,
    subject="测试商品",
    body="测试商品描述",
    channel_extra={"pay_data_type": "code_url"},
)
resp = LsPayClient.execute(req)
print(resp.get_error_info() if resp.is_error() else resp.get_data())
```

### 2.1 cashier 收银台

`channel_extra.pay_data_type=cashier` 时网关直接返回立收通收银台页面，不返回 JSON，不能用 `LsPayClient.execute` 调用（会直接报错）。服务端只签名，把自动提交的表单作为用户“去支付”请求的响应返回，浏览器加载后立即 POST 到网关并跳转收银台，用户无需再点击。

```python
req = UnifiedOrderRequest(
    mch_order_no=order_no,
    way_code=WayCode.ALI_QR,
    amount=100,
    subject="会员月卡",
    body="会员月卡 1 个月",
    channel_extra={"pay_data_type": "cashier"},
)
form = LsPayClient.build_cashier_form(req, "default")  # 只签名，不发请求

# Flask / Django 视图里直接返回 HTML，自带 submit()，浏览器直接跳转收银台
return form.to_html()
```

前后端分离时，后端返回 `form.action`、`form.method` 和 `form.fields`，前端拿到后立即创建隐藏表单并 `submit()`。支付结果仍以异步通知或订单查询为准。

## 3. 多商户

```python
LsPay.init("default", MerConfig(...))
LsPay.init("merch_b", MerConfig(...))
resp = LsPayClient.execute(req, "merch_b")
```

## 4. Demo

```bash
cd python/examples
python unified_order_request_demo.py
python query_order_request_demo.py
python refund_request_demo.py
python query_refund_request_demo.py
python notify_verify_demo.py
python balance_demo.py
python delay_trans_demo.py
python quick_pay_demo.py
python electronic_account_demo.py
python scenarios/a_qr_demo.py
python scenarios/a_lite_h5_demo.py
python scenarios/w_pub_demo.py
python scenarios/w_lite_h5_demo.py
python scenarios/cup_qr_demo.py
```

## 5. 全局配置

| 字段                  | 默认值                          | 说明                          |
|-----------------------|---------------------------------|-------------------------------|
| `LsPay.GATEWAY`       | `"https://api.lspay.tech/"`     | 网关基础地址                  |
| `LsPay.CONNECT_MS`    | `10000`                         | 连接超时                      |
| `LsPay.READ_MS`       | `15000`                         | 读取超时                      |
| `LsPay.DEBUG`         | `False`                         | 打印请求 / 响应               |
| `LsPay.PROD_MODE`     | `True`                          | 生产环境标记                  |
| `LsPay.INSECURE`      | `False`                         | 关闭 SSL 校验                 |
| `LsPay.LOGGER`        | `None`                          | 自定义日志回调                |

## 6. 异常体系

```
LsPayException
├─ LsPayConfigException
├─ LsPayNetworkException
├─ LsPaySignException
├─ LsPayValidationException
├─ LsPayProtocolException
└─ LsPayBizException(code, msg, raw)
```

## 7. 兼容 dict 调用

所有已注册端点的 dict 调用都会先经 `RequestFactory` 转换为类型化请求，因此不会绕过固定 `way_code` 或参数校验：

```python
resp = LsPayClient.execute({
    "funcCode": Endpoint.QUERY_ORDER,
    "params": {"mch_order_no": "ORDER_001"},
})
```

未知业务参数会进入 `extend_infos` 透传；公共签名字段和已声明字段不能被覆盖。

## 8. channel_extra 构造器

`lspay.request` 导出统一下单/余额分账、快捷绑卡/解绑、电子账户开户/结算卡/结算和提现配置构造器。请求也兼容直接传入 `dict`，发送前统一压缩为稳定 JSON 并执行相同校验。

## 9. 通知验签

```python
notify = LsPayNotify.parse(request.form)  # Flask 示例
if isinstance(notify, PaymentNotify):
    notify.assert_order(local_order_no, local_amount, "cny")
    if notify.is_succeeded():
        process_once(notify.get_idempotency_key())
return LsPayNotify.success_response()  # 必须精确返回 "ok"
```

验签覆盖通知中的全部字段，并校验 `mch_no/app_id` 与当前商户配置一致。

## 10. 测试与编译检查

```bash
cd python
python -m unittest discover -s tests -v
python -m compileall -q lspay examples tests
```
