# 立收通 (LSPay) 支付渠道 SDK

本仓库提供 PHP / Java / Python / C# / UniApp / Go 六种实现，统一封装立收通支付网关的
通信、签名 / 验签、商户配置、错误处理、Request 对象和 Demo。

六种 SDK 均覆盖支付、分账、快捷支付、电子账户、余额业务和通知验签。
`contracts/` 是跨语言接口与支付方式覆盖基线。

---

## 1. 版本要求

| 语言     | 版本                |
|----------|---------------------|
| PHP      | 7.4+                |
| Java     | JDK 1.8+ (Maven)    |
| Python   | 3.7+                |
| C# / .NET | netstandard2.0；测试与 Demo 使用 .NET 8 |
| UniApp / JS | Node 14+ / ES6+   |
| Go       | 1.18+，仅标准库    |

### 1.1 功能矩阵

| 能力 | PHP | Java | Python | C# | UniApp | Go |
| --- | --- | --- | --- | --- | --- | --- |
| 统一下单、订单查询、退款、退款查询 | 完整 | 完整 | 完整 | 完整 | 完整 | 完整 |
| 支付/退款/转账通知验签 | 完整 | 完整 | 完整 | 完整 | 完整 | 完整 |
| 订单分账与延时分账 | 完整 | 完整 | 完整 | 完整 | 完整 | 完整 |
| 快捷支付 | 完整 | 完整 | 完整 | 完整 | 完整 | 完整 |
| 电子账户 | 完整 | 完整 | 完整 | 完整 | 完整 | 完整 |
| 余额支付与余额查询 | 完整 | 完整 | 完整 | 完整 | 完整 | 完整 |
| Web 调试面板 | 完整 | 不适用 | 不适用 | 不适用 | 不适用 | 不适用 |

---

## 2. 仓库结构

```
lspay-sdk/
├─ php/
│   ├─ LsPaySdk/         核心包：config/core/enums/exceptions/request
│   └─ LsPayDemo/        演示项目：loader + 各接口 / 各场景 demo
├─ java/                 Maven 项目；包结构 tech.lspay.sdk.{config,core,enums,exceptions,request,example}
├─ python/               lspay/{config,core,enums,exceptions,request} + examples/
├─ csharp/               netstandard2.0 SDK + .NET 8 Demo / 协议测试
├─ uniapp/               lspay/{config,core,enums,exceptions,request} + example/ + pages/pay/pay.vue
├─ go/                   Go 模块 lspay.tech/lspay-sdk-go + examples/
├─ contracts/            六种语言共享的接口与 way_code 覆盖清单
├─ docs/                 接口地址与 way_code 清单
├─ _verify/              跨语言签名比对与接口覆盖检查
└─ WORK_FLOW.md          PHP 调用、配置加载与通知处理流程
```

---

## 3. 支付业务

| 功能         | 接口路径                          | Request 类             |
|--------------|-----------------------------------|------------------------|
| 统一下单     | `gateway/unifiedorder`            | `UnifiedOrderRequest`  |
| 订单查询     | `gateway/query`                   | `QueryOrderRequest`    |
| 订单退款     | `gateway/refund`                  | `RefundRequest`        |
| 退款订单查询 | `gateway/refund/query`            | `QueryRefundRequest`   |

全部接口地址见 `docs/请求接口地址.md`。

所有请求和响应日志都会对签名、密钥、账户、银行卡、证件、手机号、短信验证码和渠道绑卡标识脱敏，下文不再逐个说明。

### 3.1 统一下单参数

| 字段          | 类型   | 必填 | 说明                                                     |
|---------------|--------|------|----------------------------------------------------------|
| `mch_no`      | string | Y    | 商户号（由 `LsPay.init` 传入，自动注入）                 |
| `app_id`      | string | Y    | 支付应用 APPID（同上自动注入）                           |
| `mch_order_no`| string | Y    | 商户订单号                                               |
| `way_code`    | string | Y    | 支付方式代码（参考 `WayCodeEnum`）                       |
| `amount`      | int    | Y    | 金额，**单位：分**                                       |
| `currency`    | string | N    | 货币类型，默认 `cny`                                     |
| `subject`     | string | Y    | 商品标题                                                 |
| `body`        | string | Y    | 商品描述                                                 |
| `req_time`    | string | Y    | 请求时间戳（13 位毫秒，SDK 自动注入）                    |
| `channel_extra`| string| Y    | 渠道扩展参数（JSON，可传 `pay_data_type` / `openid` / `auth_code` 等） |
| `version`     | string | Y    | 接口版本号 `1.0`（SDK 自动注入）                         |
| `sign_type`   | string | Y    | 签名类型 `MD5`（SDK 自动注入）                           |
| `sign`        | string | Y    | 签名（SDK 自动计算）                                     |
| `client_ip`   | string | N    | 客户端 IP                                                |
| `notify_url`  | string | N    | 异步通知地址                                             |
| `return_url`  | string | N    | 同步跳转地址                                             |
| `expired_time`| int    | N    | 订单有效期，单位秒；平台默认 7200 秒                     |
| `division_mode`| int   | N    | `0` 不分账、`1` 实时分账、`2` 延时分账                  |
| `ext_param`   | string | N    | 商户自定义参数，回调原样返回                             |
| `polling_mode`| int    | N    | 通道轮询：`0` 关闭、`1` 自然轮询、`2` 权重轮询、`3` 防断链轮询 |

`md5_key` 只用于签名，不放进请求体。业务方只传业务字段，公共字段和 `sign` 由 SDK 注入。

`channel_extra.pay_data_type=cashier` 时网关直接返回立收通收银台页面，不返回 JSON，不能在服务端调用统一下单接口。服务端只生成签名表单，交给用户浏览器 POST 到网关；用普通接口调用发 cashier 会直接报错。

| 语言 | 生成表单 | 输出自动提交的 HTML |
|------|----------|--------------------|
| PHP | `$client->buildCashierForm($req)` | `$client->renderCashierForm($req)` |
| Java | `LsPayClient.buildCashierForm(req)` | `form.toHtml()` |
| Python | `LsPayClient.build_cashier_form(req)` | `form.to_html()` |
| C# | `client.BuildCashierForm(req)` | `form.ToHtml()` |
| Go | `client.BuildCashierForm(req)` | `form.HTML()` |
| UniApp / Node.js | `LsPayClient.buildCashierForm(req)` | `form.toHtml()` |

生成的表单包含 `action`（统一下单地址）、`method`（`POST`）和已签名的全部字段。PHP 示例见 `php/LsPayDemo/v1/payment/create/CashierFormDemo.php`。

### 3.2 下单时分账

订单分账使用统一下单端点。当 `division_mode` 为 `1` 或 `2` 时，
`channel_extra.div_list` 必填且不能为空。每个接收方包含：

- `trans_in`：必填，分账接收方用户 ID。
- `div_amt`：可选，大于 0 的整数，单位分；不传时按平台配置比例分账。
- `description`：可选，分账说明。

PHP 推荐使用类型化构造器，避免手写 JSON 字段错误：

```php
use LsPaySdk\enums\PayDataTypeEnum;
use LsPaySdk\request\DivisionReceiver;
use LsPaySdk\request\UnifiedOrderChannelExtra;

$extra = (new UnifiedOrderChannelExtra())
    ->addDivisionReceiver(
        (new DivisionReceiver('RECEIVER_USER_ID'))
            ->setDivAmt(10)
            ->setDescription('经销商订单分账')
    )
    ->setPayDataType(PayDataTypeEnum::CODE_IMG_URL);

$request->setDivisionMode(1)->setChannelExtra($extra);
```

### 3.3 延时分账交易确认

- 接口：`gateway/delaytrans/confirm`
- 请求类：`DelayTransConfirmRequest`
- 响应类：`DelayTransConfirmResponse`
- `pay_order_id` 与 `mch_order_no` 至少填写一个。
- `order_id` 为必填的商户确认流水号，最长 30 字符。
- 可通过 `DivisionChannelExtra` 设置本次确认的 `div_list`；列表总金额不能超过原交易可分账金额。
- `status=200` 只表示接口调用成功；应通过 `$response->isSuccessful()` 判断 `trans_status` 是否为 `S`。

### 3.4 交易确认退款

- 接口：`gateway/delaytrans/confirmrefund`
- 请求类：`DelayTransConfirmRefundRequest`
- 响应类：`DelayTransConfirmRefundResponse`
- `confirm_order_id` 与 `confirm_refund_no` 均为必填，最长 30 字符。
- `channel_extra` 为可选 JSON 对象字符串，SDK 会统一压缩后参与签名。
- `status=200` 只表示接口调用成功；应通过 `$response->isSuccessful()` 判断 `trans_status` 是否为 `S`。
- 确认退款成功后，仍可调用订单退款接口处理原支付订单退款。

### 3.5 所有接口的公共响应状态

每个接口都先返回统一外层响应，再由具体 `XxxResponse` 解释 `data` 内的业务状态：

- `status`：必填；`200` 表示接口处理成功，其他值表示处理有误。
- `msg`：可选，具体处理结果或错误原因。
- `sign`：可选，对 `data` 的签名；同步响应按平台规则只透传、不验签。
- `data`：可选，接口业务数据；SDK 同时兼容 JSON 对象和 JSON 字符串。

五种语言均通过公共响应基类提供等价的 `status/msg/sign/data/isError` 能力；
旧版 `getCode` 保留为 `getStatus` 的兼容别名。

### 3.6 可转余额查询

- 接口：`gateway/delaytrans/unconfirm/query`
- 请求类：`DelayTransUnconfirmQueryRequest`
- 响应类：`DelayTransUnconfirmQueryResponse`
- 本接口没有业务请求字段，公共参数和签名全部由 SDK 自动注入。
- `$response->getUnconfirmAmount()` 返回所有延时分账订单的未分账总金额，单位为分。

### 3.7 延时分账订单转账

- 接口：`gateway/delaytrans/createtask`
- 请求类：`DelayTransCreateTaskRequest`
- 响应类：`DelayTransCreateTaskResponse`
- 必填字段：电子账号、账号姓名、商户流水号和转账金额（单位：分）。
- 同步响应 `data.status=PENDING` 仅表示任务已创建，最终结果以异步通知为准。
- 转账通知由 `DelayTransTransferNotify` 处理，支持 `PENDING/PROCESSING/COMPLETED/FAILED/PARTIAL`。
- 转账通知必须带 `sign`，缺少时按验签失败处理。
- 除 `sign`、空字符串和 `null` 外，所有收到的通知字段（包括未知新增字段）均参与验签。

### 3.8 快捷支付绑卡申请

- 接口：`gateway/quickpay/buckleapply`
- 请求类：`QuickPayBindApplyRequest`
- 渠道参数类：`QuickPayBindChannelExtra`
- 响应类：`QuickPayBindApplyResponse`
- `way_code` 固定为 `BANK_FAST`，由请求类自动设置。
- `channel_extra` 包含银行卡、姓名、证件和银行预留手机号等敏感信息，只能在服务端处理。
- SDK 会校验证件有效期、卡类型等字段。
- 响应兼容 `status/msg/data` 外层和直接返回业务字段两种格式。

### 3.9 快捷支付绑卡确认

- 接口：`gateway/quickpay/buckleconfirm`
- 请求类：`QuickPayBindConfirmRequest`
- 渠道参数类：`QuickPayBindConfirmChannelExtra`
- 响应类：`QuickPayBindConfirmResponse`
- `order_id` 和持卡人四要素必须与绑卡申请保持一致。
- 验证码接受 3–8 位数字。
- 只有 `$response->hasBinding()` 为 `true` 时才能保存 `channel_user` 与 `channel_card_id`。

### 3.10 快捷支付申请

- 接口：复用统一下单 `gateway/unifiedorder`
- 请求类：`QuickPayApplyRequest`
- 响应类：`QuickPayApplyResponse`
- `way_code` 固定为 `BANK_FAST`。
- `channel_extra.channel_user` 与 `channel_extra.channel_card_id` 均为必填，取自绑卡确认成功响应。
- `trans_status=P` 表示申请已受理并等待短信确认，不代表支付失败。
- 最终支付结果仍以 `order_state=2` 或验签后的支付异步通知为准。

### 3.11 快捷支付确认

- 接口：`gateway/quickpay/payconfirm`
- 请求类：`QuickPayConfirmRequest`
- 响应类：`QuickPayConfirmResponse`
- `way_code` 固定为 `BANK_FAST`。
- `pay_order_id` 与 `mch_order_no` 至少填写一个，短信验证码必须是6位数字。
- `trans_status=P` 表示仍在支付验证中，最终结果以验签后的支付异步通知为准。

### 3.12 快捷支付解绑

- 接口：`gateway/quickpay/payunbind`
- 请求类：`QuickPayUnbindRequest`
- 渠道参数类：`QuickPayUnbindChannelExtra`
- 响应类：`QuickPayUnbindResponse`
- `way_code` 固定为 `BANK_FAST`。
- `channel_extra.mch_user_id` 与 `channel_extra.channel_card_id` 均为必填。

### 3.13 快捷支付短信校验

- 接口：`gateway/quickpay/smscheck`
- 请求类：`QuickPaySmsCheckRequest`
- 响应类：`QuickPaySmsCheckResponse`
- 字段校验与支付确认一致：`way_code=BANK_FAST`、订单号二选一、6位短信验证码。
- 该接口只预校验验证码；成功后仍需调用快捷支付确认接口完成支付。
- `trans_status=P` 表示正在验证。

### 3.14 电子账户用户开通

- 接口：`gateway/user/personapply`
- 请求类：`ElectronicAccountUserOpenRequest`
- 个人资料类：`ElectronicAccountUserInfo`
- 企业/个体户资料类：`ElectronicAccountMerchantInfo`
- 渠道参数类：`ElectronicAccountOpenChannelExtra`
- 响应类：`ElectronicAccountUserOpenResponse`
- `way_code` 固定为 `APPLY`；支持个人、企业和个体户三种账户类型。
- 个人必须传 `channel_extra.user_info`，企业和个体户必须传 `channel_extra.mer_info`。
- `channel_extra` 最长 2048 字符（其他接口为 256），业务开通和业务变更同样如此。

### 3.15 电子账户业务开通

- 接口：`gateway/user/personconfirm`
- 请求类：`ElectronicAccountBusinessOpenRequest`
- 结算卡类：`ElectronicAccountSettlementCard`
- 结算配置类：`ElectronicAccountSettleConfig`
- 提现配置类：`ElectronicAccountCashConfig`
- 渠道参数类：`ElectronicAccountBusinessChannelExtra`
- 响应类：`ElectronicAccountBusinessOpenResponse`
- `channel_extra` 必须包含结算卡、结算周期和至少一条提现配置。
- SDK 会区分对公/对私结算卡规则，并校验证件有效期、地区编码和提现枚举。

### 3.16 电子账户业务变更

- 接口：`gateway/user/personmodify`
- 请求类：`ElectronicAccountBusinessChangeRequest`
- 简化结算卡类：`ElectronicAccountModifiedCard`
- 渠道参数类：`ElectronicAccountBusinessChangeChannelExtra`
- 响应类：`ElectronicAccountBusinessChangeResponse`
- 复用结算周期和提现配置类，支持多条提现配置。
- 业务变更的 `card_info` 仅包含卡类型、开户名称和卡号，与业务开通结构不同。

### 3.17 电子账户用户查询

- 接口：`gateway/user/query`
- 请求类：`ElectronicAccountUserQueryRequest`
- 响应类：`ElectronicAccountUserQueryResponse`
- 查询成功看 `trans_status=S`。开户成功看开户接口的 `trans_status=S`。
- 返回资料里的 `channel_status`：`0` 待入驻、`1` 入驻成功、`2` 入驻失败。`$response->isOnboarded()` 只表示该字段为 `1`。
- `channel_extra` 支持原始读取和通过 `getChannelExtraArray()` 解析。

### 3.18 电子账户结算与提现

- `ElectronicAccountSettlementQueryRequest`：结算记录查询。
- `ElectronicAccountBalanceWithdrawRequest`：余额提现。
- `ElectronicAccountSingleWithdrawQueryRequest`：单笔提现查询。
- `ElectronicAccountBatchWithdrawQueryRequest`：批量提现查询。
- 查询响应提供类型化金额、分页及交易状态读取方法。
- 批量提现记录从 `data.data` 读取。
- `succeess` 是部分提现接口定义的成功状态值（不是笔误），SDK 原样保留，并兼容 `success`。

### 3.19 余额支付

- `BalancePayRequest`：通过 `gateway/unifiedorder` 发起余额支付。
- `BalancePayChannelExtra`：设置出款账户与收款列表。
- `division_mode` 固定为 `1`，`way_code` 固定为 `BALANCE_PAY`。
- SDK 校验每个收款金额，并确保总额不超过订单金额。

### 3.20 余额查询

- 接口：`gateway/user/balance/query`
- 请求类：`BalanceQueryRequest`
- 响应类：`BalanceQueryResponse`
- `way_code` 固定为 `BALANCE_QUERY`。
- 同时兼容公共 `status/msg/data` 包装与平台扁平余额响应。

---

## 4. 统一调用模型

```
   配置数组 / JSON 文件 / LSPAY_* 环境变量
            │
            ▼
        LsPay::init(config, true, profile)    ←─ 多商户、可注册多份
            │
            ▼
      (new LsPayClient(profile))->postRequest(XxxRequest)
            │
            ▼
      LsPay.post(path, params, profile)
            │
            ▼ (签名 / 拼接公共字段 / HTTP POST)
       LsPayResponse  (.isError / .getData / .getErrorInfo)
```

各语言保持相近的请求字段命名；调用入口以各语言 README 为准：

| 操作       | PHP | Java | Python | C# | UniApp / JS | Go |
|------------|-----|------|--------|----|-------------|----|
| 注册商户   | `LsPay::init($cfg, true, 'default')` | `LsPay.init("default", cfg)` | `LsPay.init("default", MerConfig(...))` | `options.AddMerchant("default", cfg)` | `LsPay.init('default', new MerConfig(...))` | `lspay.NewClient(cfg)` |
| 构造请求   | `new UnifiedOrderRequest()->set...()` | `new UnifiedOrderRequest().set...()` | `UnifiedOrderRequest(...)` | `new UnifiedOrderRequest { ... }` | `new UnifiedOrderRequest().set...()` | `&lspay.UnifiedOrderRequest{...}` |
| 执行       | `(new LsPayClient('default'))->postRequest($req)` | `LsPayClient.execute(req, "default")` | `LsPayClient.execute(req, "default")` | `await client.PostAsync(req)` | `await LsPayClient.execute(req)` | `client.UnifiedOrder(ctx, req)` |
| 取结果     | `$resp->isError()/getData()` | `resp.isError()/getData()` | `resp.is_error()/get_data()` | `resp.IsError/resp.Data` | `resp.isError()/getData()` | `resp.IsError()/resp.Data()` |

---

## 5. 快速开始

### 5.1 PHP

推荐使用 Composer：

```bash
cd php
composer install
```

```php
require_once __DIR__ . '/vendor/autoload.php';
// 未使用 Composer 时可改为：require_once __DIR__ . '/LsPaySdk/init.php';
use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\request\UnifiedOrderRequest;

LsPay::init([
    'mch_no'    => '你的商户号',
    'app_id'    => '你的应用ID',
    'md5_key'   => '你的MD5密钥',
    'notify_url'=> 'https://your.domain/notify',
], true, 'default');

$req = (new UnifiedOrderRequest())
    ->setMchOrderNo('JT' . time() . rand(1000, 9999))
    ->setWayCode(WayCodeEnum::ALI_QR)
    ->setAmount(1)
    ->setSubject('测试商品')
    ->setBody('测试商品描述')
    ->setChannelExtra(['pay_data_type' => 'code_url']);

$resp = (new LsPayClient('default'))->postRequest($req);
echo $resp->isError() ? $resp->getErrorInfo() : json_encode($resp->getData());
```

完整 Demo:
- `php/LsPayDemo/UnifiedOrderRequestDemo.php`（统一下单）
- `php/LsPayDemo/QueryOrderRequestDemo.php`（订单查询）
- `php/LsPayDemo/RefundRequestDemo.php`（退款）
- `php/LsPayDemo/QueryRefundRequestDemo.php`（退款查询）
- `php/LsPayDemo/v1/payment/create/*Demo.php`（场景化）
- `php/LsPayDemo/v1/payment/division/OrderDivisionDemo.php`（下单时分账）
- `php/LsPayDemo/v1/payment/division/DelayTransConfirmDemo.php`（延时分账交易确认）
- `php/LsPayDemo/v1/payment/division/DelayTransConfirmRefundDemo.php`（交易确认退款）
- `php/LsPayDemo/v1/payment/division/DelayTransUnconfirmQueryDemo.php`（可转余额查询）
- `php/LsPayDemo/v1/payment/division/DelayTransCreateTaskDemo.php`（延时分账订单转账）
- `php/LsPayDemo/v1/quickpay/bind/BindApplyDemo.php`（快捷支付绑卡申请）
- `php/LsPayDemo/v1/quickpay/bind/BindConfirmDemo.php`（快捷支付绑卡确认）
- `php/LsPayDemo/v1/quickpay/payment/PaymentApplyDemo.php`（快捷支付申请）
- `php/LsPayDemo/v1/quickpay/payment/PaymentConfirmDemo.php`（快捷支付确认）
- `php/LsPayDemo/v1/quickpay/bind/UnbindDemo.php`（快捷支付解绑）
- `php/LsPayDemo/v1/quickpay/payment/SmsCheckDemo.php`（快捷支付短信校验）
- `php/LsPayDemo/v1/balancepay/BalancePayDemo.php`（余额支付）
- `php/LsPayDemo/v1/balancepay/BalanceQueryDemo.php`（余额查询）
- `php/LsPayDemo/v1/electronicaccount/user/PersonalUserOpenDemo.php`（个人电子账户开通）
- `php/LsPayDemo/v1/electronicaccount/user/EnterpriseUserOpenDemo.php`（企业电子账户开通）
- `php/LsPayDemo/v1/electronicaccount/business/BusinessOpenDemo.php`（电子账户业务开通）
- `php/LsPayDemo/v1/electronicaccount/business/BusinessChangeDemo.php`（电子账户业务变更）
- `php/LsPayDemo/v1/electronicaccount/user/UserQueryDemo.php`（电子账户用户查询）
- `php/LsPayDemo/v1/electronicaccount/settlement/SettlementQueryDemo.php`（电子账户结算记录查询）
- `php/LsPayDemo/v1/electronicaccount/withdraw/BalanceWithdrawDemo.php`（电子账户余额提现）
- `php/LsPayDemo/v1/electronicaccount/withdraw/SingleWithdrawQueryDemo.php`（电子账户单笔提现查询）
- `php/LsPayDemo/v1/electronicaccount/withdraw/BatchWithdrawQueryDemo.php`（电子账户批量提现查询）
- **`php/LsPayDemo/web/index.php`** —— 浏览器 Web 调试面板，按业务折叠分组，支持商户切换和干跑模式预览签名

调试面板安全约束：

- 默认仅允许 `127.0.0.1` / `::1` 访问。
- 远程访问需设置 `LSPAY_DEBUG_PANEL_ALLOW_REMOTE=1`，并建议同时设置强随机 `LSPAY_DEBUG_PANEL_TOKEN`，通过 `?token=...` 访问。
- 默认锁定真实请求；在 `config.json` 中设置 `"debug_panel_allow_live": true`（或设置环境变量 `LSPAY_DEBUG_PANEL_ALLOW_LIVE=1`），并在页面二次确认后才会发送。
- `config.json`、`config_merchant_b.json` 和日志目录已加入 `.gitignore`；多商户调试时复制 `config_merchant_b.example.json` 为 `config_merchant_b.json` 并填写真实配置。生产环境优先使用 `LSPAY_*` 环境变量或密钥管理服务。
- 配置为空或仍是 `YOUR_*` / `MERCH_*_` 示例占位符时，面板会在顶部提示，且不会把占位符填入公共请求参数。

HTTP 传输可按客户端实例配置：

```php
$client = (new LsPayClient('default'))
    ->setConnectTimeout(5)
    ->setRequestTimeout(30)
    ->setUserAgent('YourApp/1.0');
```

SDK 不自动重试支付、退款、提现或转账请求，避免网络超时后重复扣款。业务方应使用唯一订单号查询原请求结果，再决定是否重试。非 JSON 响应会抛出 `LsPayProtocolException`，HTTP/cURL 错误会抛出包含结构化状态的 `LsPayNetworkException`。

### 5.2 Java

```java
MerConfig cfg = new MerConfig();
cfg.mchNo  = "你的商户号";
cfg.appId  = "你的应用ID";
cfg.md5Key = "你的MD5密钥";
LsPay.init("default", cfg);

UnifiedOrderRequest req = new UnifiedOrderRequest()
        .setMchOrderNo("JT" + System.currentTimeMillis())
        .setWayCode(WayCodeEnum.ALI_QR)
        .setAmount(1)
        .setSubject("测试商品")
        .setBody("测试商品描述")
        .setChannelExtra("{\"pay_data_type\":\"code_url\"}");
LsPayResponse resp = LsPayClient.execute(req);
if (resp.isError()) System.out.println(resp.getErrorInfo());
else System.out.println(resp.getData());
```

构建与运行 Demo：

```bash
cd java
mvn clean package
mvn exec:java -Dexec.mainClass=tech.lspay.sdk.example.UnifiedOrderRequestDemo
```

Demo 类列表见 `java/README.md`。

### 5.3 Python

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
    body="测试商品说明",
    channel_extra={"pay_data_type": "code_url"},
)
resp = LsPayClient.execute(req)
print(resp.get_error_info() if resp.is_error() else resp.get_data())
```

Demo：

```bash
cd python/examples
python unified_order_request_demo.py
python query_order_request_demo.py
python refund_request_demo.py
python query_refund_request_demo.py
python notify_verify_demo.py
python scenarios/a_qr_demo.py
python scenarios/a_lite_h5_demo.py
python scenarios/w_pub_demo.py
python scenarios/w_lite_h5_demo.py
python scenarios/cup_qr_demo.py
```

### 5.4 C# / .NET

```csharp
var options = new LsPayOptions()
    .AddMerchant("default", new MerchantConfig
    {
        MchNo = "...",
        AppId = "...",
        Md5Key = "..."
    });
using var client = new LsPayClient(options);
var resp = await client.PostAsync(new UnifiedOrderRequest
{
    MchOrderNo = "JT" + DateTimeOffset.UtcNow.ToUnixTimeMilliseconds(),
    WayCode = WayCode.ALI_QR,
    Amount = 1,
    Subject = "测试商品",
    Body = "测试商品说明",
    ChannelExtra = new UnifiedOrderChannelExtra().PayData(PayDataType.CodeUrl)
});
```

完整配置、通知验签和测试命令见 `csharp/README.md`。

### 5.5 UniApp / Node.js

```js
const { LsPay, LsPayClient, MerConfig, WayCode, UnifiedOrderRequest } = require('./uniapp/lspay');

LsPay.init('default', new MerConfig({
  mchNo: '...', appId: '...', md5Key: '...',
  notifyUrl: 'https://your.domain/notify',
}));

const req = new UnifiedOrderRequest()
  .setMchOrderNo('JT' + Date.now())
  .setWayCode(WayCode.ALI_QR)
  .setAmount(1)
  .setSubject('测试')
  .setBody('测试商品说明')
  .setChannelExtra({ pay_data_type: 'code_url' });
const resp = await LsPayClient.execute(req);
console.log(resp.isError() ? resp.getErrorInfo() : resp.getData());
```

UniApp 页面 Demo：`uniapp/example/pages/pay/pay.vue`（包含支付宝当面付 / 微信 Native / 查询 / 退款 / 退款查询 5 个按钮）。

### 5.6 Go

```go
import lspay "lspay.tech/lspay-sdk-go"

client, err := lspay.NewClient(lspay.MerchantConfig{
    MchNo: "...", AppID: "...", MD5Key: "...",
    NotifyURL: "https://your.domain/notify",
})
if err != nil {
    log.Fatal(err)
}
resp, err := client.UnifiedOrder(ctx, &lspay.UnifiedOrderRequest{
    MchOrderNo:   fmt.Sprintf("JT%d", time.Now().Unix()),
    WayCode:      lspay.WayCodeAliQR,
    Amount:       1,
    Subject:      "测试商品",
    Body:         "测试商品说明",
    ChannelExtra: &lspay.UnifiedOrderChannelExtra{PayDataType: lspay.PayDataTypeCodeImgURL},
})
if err == nil {
    err = resp.Err()
}
```

安装方式、通知处理和示例见 `go/README.md`。

---

## 6. 多商户切换

各语言均通过 profile 选择商户；PHP 写法如下：

```php
LsPay::init($cfgA, true, 'default');
LsPay::init($cfgB, true, 'merch_b');
$resp = (new LsPayClient('merch_b'))->postRequest($req);
```

```java
LsPay.init("default", cfgA);
LsPay.init("merch_b", cfgB);
LsPayResponse resp = LsPayClient.execute(req, "merch_b");
```

```python
LsPay.init("default", MerConfig(...))
LsPay.init("merch_b", MerConfig(...))
resp = LsPayClient.execute(req, "merch_b")
```

```js
LsPay.init('default', new MerConfig(...));
LsPay.init('merch_b', new MerConfig(...));
const resp = await LsPayClient.execute(req, 'merch_b');
```

Go 不使用全局 profile，每个商户创建一个 `Client`：

```go
clientA, _ := lspay.NewClient(cfgA)
clientB, _ := lspay.NewClient(cfgB)
resp, err := clientB.UnifiedOrder(ctx, req)
```

---

## 7. 异常体系

PHP、Python、C#：

```
LsPayException
├─ LsPayConfigException      配置错误 / 密钥缺失
├─ LsPayValidationException  请求或通知字段不符合协议
├─ LsPayNetworkException     网络 / HTTP 错误
├─ LsPayProtocolException    响应不是合法 JSON
├─ LsPaySignException        签名 / 验签失败
└─ LsPayBizException         网关业务级错误（含 code / msg / raw）
```

Java 没有 `LsPayValidationException` 和 `LsPayProtocolException`：参数不合规抛 `IllegalArgumentException`，响应不是 JSON 时抛 fastjson 的 `JSONException`。UniApp / JS 没有 `LsPayProtocolException`。

Go 不用异常，所有错误都是 `*lspay.Error`，用 `Kind` 区分 config、validation、network、protocol、sign、biz 六类，见 `go/README.md`。

---

## 8. 工具方法

| 方法                   | 说明                          |
|------------------------|-------------------------------|
| `LsPayTools.sign`      | 立收通 MD5 签名（自动拼 `&key=md5_key`）|
| `LsPayTools.verifySign`| 校验异步通知 / 同步回跳签名   |
| `LsPayTools.toFen`     | 元 → 分                       |
| `LsPayTools.toYuan`    | 分 → 元                       |
| `LsPayTools.msecTime`  | 13 位毫秒级时间戳             |

Go 对应的是包级函数 `lspay.Sign`、`lspay.VerifySign`、`lspay.YuanToFen`、`lspay.FenToYuan`。

### 8.1 立收通官方签名算法

> 见 [`doc.lspay.tech/1epkk6okpd/jiekouqianmingsuanfa.html`](https://doc.lspay.tech/1epkk6okpd/jiekouqianmingsuanfa.html)

1. 移除 `sign` 字段，不参与签名
2. 过滤值为 `''` / `null` 的字段
3. 剩余字段按 **参数名 ASCII 升序**
4. 按 `k=v&k=v` 顺序拼接（**禁止 URL 编码**）；如果某个值是数组 / 对象，**先 JSON 序列化**（PHP: `json_encode`；Python: `json.dumps`；JS: `JSON.stringify`；Java: `JSON.toJSONString`；C#: `JsonSerializer.Serialize`，均使用紧凑 JSON）
5. 末尾追加 `&key={md5_key}`（**md5_key 是商户应用密钥**）
6. 对整串做 `md5`，结果 `toUpperCase()`

### 8.2 PHP 异步通知

```php
$notify = \LsPaySdk\notify\LsPayNotify::fromGlobals('default');

if ($notify instanceof \LsPaySdk\notify\PaymentNotify) {
    $notify->assertOrder($orderNo, $amountFen, $currency);
} elseif ($notify instanceof \LsPaySdk\notify\RefundNotify) {
    $notify->assertRefund($refundNo, $refundFen, $currency, $payFen);
}
```

- SDK 使用收到的全部表单字段验签，并核对 `mch_no/app_id`。
- 支持支付、退款及延时分账订单转账通知；转账通知可通过处理金额构造进度级幂等键。
- 使用 `getIdempotencyKey()` 建立业务唯一键；重复通知应直接返回成功。
- 只有数据库事务提交完成后才能返回 `LsPayNotify::successResponse()`（精确字符串 `ok`）。
- 完整安全处理骨架见 `php/LsPayDemo/NotifyVerifyDemo.php`。

---

## 9. 自检脚本

| 语言         | 命令                                                                     |
|--------------|--------------------------------------------------------------------------|
| PHP          | `php php/tests/run.php`                                                   |
| Java         | `cd java && mvn verify`                                                   |
| Python       | `cd python && python -m unittest discover -s tests -v`                    |
| C#           | `dotnet run --project csharp/tests/LsPaySdk.Tests/LsPaySdk.Tests.csproj`  |
| UniApp / JS  | `cd uniapp && npm run verify`                                             |
| Go           | `cd go && go vet ./... && go test ./...`                                  |
| 覆盖一致性   | `python _verify/verify_language_coverage.py`                              |

跨语言签名比对（同一组数据在各语言下签名必须相同）：

```bash
php    _verify/verify_sign_official.php
php    _verify/cross_lang_php.php
python _verify/cross_lang_python.py
node   _verify/cross_lang_js.mjs
```

版本变更见 [CHANGELOG.md](CHANGELOG.md)。

---

## 10. License

MIT
