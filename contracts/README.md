# 跨语言协议清单

本目录是 PHP、Java、Python、C#、UniApp、Go 六种 SDK 的共享覆盖基线：

- `endpoints.json`：接口地址、请求类、响应类与通知处理器。
- `way-codes.json`：全部业务代码及统一下单可选择的支付方式。

新增或调整接口时，应先同步协议清单，再更新六种实现和各自测试。可在仓库根目录执行：

```bash
python _verify/verify_language_coverage.py
```

该检查只验证生产源码中的接口覆盖；字段、签名、校验和响应解析仍由各语言的协议测试验证。
