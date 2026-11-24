# 立收通 (LSPay) Go SDK

Go 1.18+，只依赖标准库。覆盖支付、退款、余额支付/查询、延时分账、快捷支付、电子账户全部接口，以及支付、退款、延时分账转账通知的验签。接口清单见 `contracts/endpoints.json`。

## 安装

SDK 没有发布到公共仓库。把 `go/` 目录放进项目，或在 `go.mod` 里指向本地路径：

```
require lspay.tech/lspay-sdk-go v0.0.0
replace lspay.tech/lspay-sdk-go => ../lspay-sdk/go
```

## 配置

商户号、应用 ID、应用密钥只能在立收通商户控台的应用信息里查看，文档和示例里的商户号都不能用。

```go
cfg, err := lspay.LoadConfig("")          // 读 LSPAY_MCH_NO、LSPAY_APP_ID、LSPAY_MD5_KEY、LSPAY_NOTIFY_URL
// 或 lspay.LoadConfig("config.json")，环境变量会覆盖文件里的值
client, err := lspay.NewClient(cfg)
```

`md5_key` 只放在服务端。多商户时每个商户创建一个 `Client`，回调地址也按商户分开。

可选项：

| 选项 | 说明 |
| --- | --- |
| `WithBaseURL(lspay.SandboxBaseURL)` | 网关地址，默认 `https://api.lspay.tech/` |
| `WithTimeout(10 * time.Second)` | 整个请求的超时，默认 30 秒 |
| `WithHTTPClient(hc)` | 自定义 `http.Client`（代理、连接池） |
| `WithLogger(func(line string) {...})` | 请求和响应日志，敏感字段已打码 |
| `WithUserAgent("MyApp/1.0")` | User-Agent |

## 统一下单

```go
resp, err := client.UnifiedOrder(ctx, &lspay.UnifiedOrderRequest{
    MchOrderNo:   "ORD202610280001",
    WayCode:      lspay.WayCodeWxPub,
    Amount:       100, // 分
    Subject:      "会员月卡",
    Body:         "会员月卡 1 个月",
    ChannelExtra: &lspay.UnifiedOrderChannelExtra{PayDataType: lspay.PayDataTypeCodeImgURL},
})
if err != nil {
    // 网络超时等错误：不要直接重试，先用同一单号调 QueryOrder
    return err
}
if err := resp.Err(); err != nil { // 外层 status 不是 200
    return err
}
fmt.Println(resp.PayOrderID(), resp.PayInfo())
```

- `NotifyURL` 不填时使用配置里的 `NotifyURL`，两者都为空时平台不会发支付通知。
- 支付结果以支付通知或 `QueryOrder` 为准，不以同步响应或前端跳转为准。
- 微信小程序（`WX_LITE`）用 `resp.PayInfoMap()` 取 `timeStamp`、`nonceStr`、`package`、`signType`、`paySign` 交给 `wx.requestPayment`，`signType` 不要写死。

`ChannelExtra` 接受三种写法，走同一套校验，并在发送前序列化为紧凑 JSON（数字保持整数，不转义中文和 `<>&`）：

```go
ChannelExtra: &lspay.UnifiedOrderChannelExtra{OpenID: "o6Bc..."}
ChannelExtra: map[string]interface{}{"openid": "o6Bc..."}
ChannelExtra: `{"openid":"o6Bc..."}`
```

## cashier 收银台

`ChannelExtra` 的 `PayDataType` 为 `cashier` 时网关直接返回立收通收银台页面，不返回 JSON，不能用 `client.UnifiedOrder` 调用（会返回校验错误）。服务端只签名，把自动提交的表单作为用户“去支付”请求的响应返回，浏览器加载后立即 POST 到网关并跳转收银台，用户无需再点击。

```go
func payHandler(w http.ResponseWriter, r *http.Request) {
    form, err := client.BuildCashierForm(&lspay.UnifiedOrderRequest{
        MchOrderNo:   orderNo,
        WayCode:      lspay.WayCodeAliQR,
        Amount:       100,
        Subject:      "会员月卡",
        Body:         "会员月卡 1 个月",
        ChannelExtra: &lspay.UnifiedOrderChannelExtra{PayDataType: lspay.PayDataTypeCashier},
    }) // 只签名，不发请求
    if err != nil {
        http.Error(w, err.Error(), http.StatusBadRequest)
        return
    }
    w.Header().Set("Content-Type", "text/html; charset=utf-8")
    io.WriteString(w, form.HTML()) // 自带 submit()，直接跳转收银台
}
```

前后端分离时，后端返回 `form.Action`、`form.Method` 和 `form.Fields`，前端拿到后立即创建隐藏表单并 `submit()`。支付结果仍以异步通知或 `QueryOrder` 为准。

## 接口与方法

| 接口 | 方法 | 请求 / 响应 |
| --- | --- | --- |
| `gateway/unifiedorder` | `UnifiedOrder` | `UnifiedOrderRequest` / `UnifiedOrderResponse` |
| `gateway/unifiedorder` | `QuickPayApply` | `QuickPayApplyRequest` / `QuickPayApplyResponse` |
| `gateway/unifiedorder` | `BalancePay` | `BalancePayRequest` / `BalancePayResponse` |
| `gateway/query` | `QueryOrder` | `QueryOrderRequest` / `QueryOrderResponse` |
| `gateway/refund` | `Refund` | `RefundRequest` / `RefundResponse` |
| `gateway/refund/query` | `QueryRefund` | `QueryRefundRequest` / `QueryRefundResponse` |
| `gateway/delaytrans/confirm` | `DelayTransConfirm` | `DelayTransConfirmRequest` / `DelayTransConfirmResponse` |
| `gateway/delaytrans/confirmrefund` | `DelayTransConfirmRefund` | `DelayTransConfirmRefundRequest` / `DelayTransConfirmRefundResponse` |
| `gateway/delaytrans/unconfirm/query` | `DelayTransUnconfirmQuery` | `DelayTransUnconfirmQueryRequest` / `DelayTransUnconfirmQueryResponse` |
| `gateway/delaytrans/createtask` | `DelayTransCreateTask` | `DelayTransCreateTaskRequest` / `DelayTransCreateTaskResponse` |
| `gateway/quickpay/buckleapply` | `QuickPayBindApply` | `QuickPayBindApplyRequest` / `QuickPayBindApplyResponse` |
| `gateway/quickpay/buckleconfirm` | `QuickPayBindConfirm` | `QuickPayBindConfirmRequest` / `QuickPayBindConfirmResponse` |
| `gateway/quickpay/smscheck` | `QuickPaySmsCheck` | `QuickPaySmsCheckRequest` / `QuickPaySmsCheckResponse` |
| `gateway/quickpay/payconfirm` | `QuickPayConfirm` | `QuickPayConfirmRequest` / `QuickPayConfirmResponse` |
| `gateway/quickpay/payunbind` | `QuickPayUnbind` | `QuickPayUnbindRequest` / `QuickPayUnbindResponse` |
| `gateway/user/personapply` | `ElectronicAccountUserOpen` | `ElectronicAccountUserOpenRequest` / `ElectronicAccountUserOpenResponse` |
| `gateway/user/personconfirm` | `ElectronicAccountBusinessOpen` | `ElectronicAccountBusinessOpenRequest` / `ElectronicAccountBusinessOpenResponse` |
| `gateway/user/personmodify` | `ElectronicAccountBusinessChange` | `ElectronicAccountBusinessChangeRequest` / `ElectronicAccountBusinessChangeResponse` |
| `gateway/user/query` | `ElectronicAccountUserQuery` | `ElectronicAccountUserQueryRequest` / `ElectronicAccountUserQueryResponse` |
| `gateway/user/balance/query` | `BalanceQuery` | `BalanceQueryRequest` / `BalanceQueryResponse` |
| `gateway/user/balance/withdraw` | `ElectronicAccountBalanceWithdraw` | `ElectronicAccountBalanceWithdrawRequest` / `ElectronicAccountBalanceWithdrawResponse` |
| `gateway/user/balance/withdraw/single/query` | `ElectronicAccountSingleWithdrawQuery` | `ElectronicAccountSingleWithdrawQueryRequest` / `ElectronicAccountSingleWithdrawQueryResponse` |
| `gateway/user/balance/withdraw/query` | `ElectronicAccountBatchWithdrawQuery` | `ElectronicAccountBatchWithdrawQueryRequest` / `ElectronicAccountBatchWithdrawQueryResponse` |
| `gateway/user/settlement/query` | `ElectronicAccountSettlementQuery` | `ElectronicAccountSettlementQueryRequest` / `ElectronicAccountSettlementQueryResponse` |

固定的 `way_code`（快捷支付 `BANK_FAST`、电子账户 `APPLY`、余额和提现等）由请求自动填写，不能修改。每个请求都有 `Validate()`，发送前也会自动校验。

业务判断用响应上的方法，不要只看外层 `status`：

- 统一下单 `IsAccepted()`、`IsPaid()`；订单查询 `IsPaid()`；退款 `IsRefunded()`。
- 延时分账确认 `IsSuccessful()`（`trans_status == "S"`），保存 `ConfirmOrderID()`。
- 快捷支付、开户、业务开通/变更 `IsSuccessful()`；绑卡确认 `HasBinding()` 为 true 才保存 `ChannelUser()`、`ChannelCardID()`。
- 用户查询的 `IsQuerySuccessful()` 只表示查询成功，`IsOnboarded()` 只表示 `channel_status == 1`，都不能用来判断开户结果。
- 提现 `IsAccepted()` 同时接受 `success` 和平台定义的 `succeess`。

金额：下单、退款、分账、转账、余额支付都是 `int64` 分；提现金额和提现、结算、余额查询结果是元字符串。换算用 `lspay.YuanToFen`、`lspay.FenToYuan`，不经过浮点数。

## 异步通知

```go
http.HandleFunc("/lspay/notify", func(w http.ResponseWriter, r *http.Request) {
    form, err := lspay.ReadNotifyForm(r) // 兼容 multipart/form-data 和 urlencoded
    if err == nil {
        var n lspay.Notify
        if n, err = client.ParseNotify(form); err == nil {
            err = handle(n) // 事务内：核对金额、按 n.IdempotencyKey() 去重、更新状态
        }
    }
    if err != nil {
        w.WriteHeader(http.StatusBadRequest)
        w.Write([]byte(lspay.NotifyFailureResponse))
        return
    }
    w.Write([]byte(lspay.NotifySuccessResponse)) // 纯文本 ok
})
```

`ParseNotify` 用收到的全部字段验签（包括 SDK 未声明的新字段），核对 `mch_no`、`app_id`，并校验字段格式。返回值用类型断言区分 `*PaymentNotify`、`*RefundNotify`、`*DelayTransTransferNotify`，分别用 `AssertOrder`、`AssertRefund`、`AssertTask` 核对本地记录。

## 错误

所有错误都是 `*lspay.Error`，用 `lspay.IsKind(err, kind)` 或 `errors.As` 判断：

| Kind | 含义 |
| --- | --- |
| `ErrKindConfig` | 商户配置缺失或非法 |
| `ErrKindValidation` | 请求或通知字段不符合协议 |
| `ErrKindNetwork` | 连接失败、超时或非 2xx（`HTTPStatus` 为状态码） |
| `ErrKindProtocol` | 响应不是合法 JSON 对象 |
| `ErrKindSign` | 通知签名缺失或错误 |
| `ErrKindBiz` | `resp.Err()` 返回的网关业务错误 |

SDK 不自动重试。下单、退款、提现、转账、余额支付、交易确认超时后，先用原单号查询，再决定是否用同一单号重试。

## 示例

示例会真实请求网关，运行前设置 `LSPAY_MCH_NO`、`LSPAY_APP_ID`、`LSPAY_MD5_KEY`、`LSPAY_NOTIFY_URL`，或用 `LSPAY_CONFIG` 指向 JSON 配置文件。

```bash
go run ./examples/unified_order               # 微信扫码下单
go run ./examples/wx_lite -openid <openid>    # 微信小程序支付
go run ./examples/query_order -order <单号>
go run ./examples/refund -order <单号> -amount 1
go run ./examples/delay_trans -order <单号> -receiver <account_no> -amount 10
go run ./examples/notify_server -addr :8080   # 接收通知
```

## 测试

```bash
cd go
go vet ./...
go test ./...
```

测试用本地 HTTP 服务模拟网关，不连接真实网关；签名用例读取 `_verify/fixtures/cross_lang.json`，与其他语言共用。
