# Changelog

## 3.0.0

- 各语言统一版本号为 3.0.0。
- 新增 Go SDK（`go/`，仅依赖标准库），覆盖全部 22 个接口、余额支付、快捷支付申请和 3 种通知，并加入接口覆盖检查与 CI。
- 完成 PHP 分账、快捷支付、电子账户、余额支付与余额查询接口。
- 增加强类型请求/响应、协议回归测试和 Web 调试面板。
- 增加 Composer PSR-4 自动加载与统一测试入口。
- 调试面板默认限制本机访问并锁定真实资金请求。
- 增强日志脱敏、参数校验和多商户支持。
- Java、Python、UniApp 同步 PHP 当前全部接口，并新增 C# SDK。
- 增加五语言接口覆盖清单、协议测试和持续集成检查。
- 移除未实现的 INI 配置模板，以及依赖旧接口、已无法运行的 `_verify` 脚本。

## 2.0.3

- 移除平台不存在的关闭订单接口及各语言占位实现。

## 2.0.2

- 统一下单公共字段统一为 `mch_no/app_id/req_time/version/sign_type/sign`，Java/Python/JS 移除 `nonce_str/timestamp`。
- 传输格式统一为 `application/x-www-form-urlencoded;charset=utf-8`，Java/Python/JS 由 JSON 改为表单。
- `UnifiedOrderRequest` 补齐 `currency / channel_extra / client_ip / expired_time / division_mode / ext_param`。
- 移除网关不支持的 `openid / auth_code / store_id / device_info` 顶层字段，改走 `channel_extra`。

## 2.0.1

- 修复签名：末尾追加 `&key={md5_key}`，数组/对象先序列化为 JSON。

## 2.0.0

- 引入 Request / Response 模型、统一客户端及多商户配置。
- 支持统一下单、订单查询、退款和退款查询。

## 1.2.0

- 新增 INI / JSON 配置加载、环境变量覆盖、多 profile。

## 1.1.1

- 修正订单查询、退款、退款查询的接口路径。

## 1.1.0

- 新增订单查询、申请退款、退款订单查询和异常体系。

## 1.0.0

- 首版：统一下单、异步通知验签。
