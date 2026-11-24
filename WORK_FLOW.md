# 立收通 SDK 调用流程（PHP）

其他语言的调用入口和多商户写法见 [README.md](README.md) 第 4、6 节。

## 一、请求时序

```
┌────────┐       1.new XxxRequest       ┌──────────────────┐
│ 业务代码 │ ─────────────────────────► │  XxxRequest      │
└────────┘                              │ (setMchOrderNo…) │
    │                                   └──────────────────┘
    │ 2.(new LsPayClient($profile))->postRequest($req)
    ▼
┌──────────────┐  3.$req->validate()，转为 {funcCode, params}
│ LsPayClient  │
└──────────────┘
    │ 4.LsPay::post(funcCode, params, profile)
    ▼
┌──────────┐
│  LsPay   │   5.按 profile 取 MerConfig (mch_no/app_id/md5_key)
│          │   6.注入公共字段 mch_no/app_id/req_time/version/sign_type
│          │   7.LsPayTools::sign($data, md5_key) → MD5 大写
│          │     ① 移除 sign       ② 过滤空值/null
│          │     ③ ksort 升序      ④ 拼 k=v&k=v
│          │     ⑤ 末尾 &key={md5_key}   ⑥ md5() → upper
│          │   8.curl POST 网关（不自动重试）
│          │   9.解析 JSON → XxxResponse
└──────────┘
    │
    ▼
┌──────────────┐  isError() / getData() / isSuccessful() 等
│ XxxResponse  │
└──────────────┘
```

## 二、配置加载

```
LsPay::init($config, $isArray, $profile)
    │
    ├─ $isArray=true：$config 是数组
    ├─ $isArray=false：$config 是 JSON 文件路径
    ├─ 环境变量覆盖（优先级最高）：
    │     LSPAY_MCH_NO / LSPAY_APP_ID / LSPAY_MD5_KEY
    │     LSPAY_NOTIFY_URL / LSPAY_RETURN_URL
    ├─ 校验 mch_no / app_id / md5_key 非空，回调地址为 HTTP(S) URL
    └─ 注册到 profile（默认 default）
```

```php
LsPay::init(__DIR__ . '/config/config.json');                  // default
LsPay::init(__DIR__ . '/config/config_b.json', false, 'merch_b');

$resp = (new LsPayClient('merch_b'))->postRequest($request);  // 用 merch_b 的密钥签名
```

## 三、异步通知处理

```
立收通网关 ──POST──► notify_url（每个商户 profile 一个独立地址）
                         │
                         ▼
          LsPayNotify::fromGlobals($profile)
            ① 全部字段验签   ② 核对 mch_no / app_id   ③ 识别通知类型
                         │
         ┌───────────────┼───────────────┬────────────┐
   PaymentNotify    RefundNotify   DelayTransTransferNotify   验签失败等异常
         │               │               │                    │
         ▼               ▼               ▼                    ▼
  数据库事务：锁行 → assertOrder / assertRefund / assertTask   返回 "fail"
            → 按 getIdempotencyKey() 去重 → 更新状态 → 提交
                         │
                         ▼
          返回 LsPayNotify::successResponse()（纯文本 "ok"）
```

完整骨架见 `php/LsPayDemo/NotifyVerifyDemo.php`。

## 四、Demo 结构

```
LsPayDemo/loader.php  ← 公共加载器（SDK + 商户配置 + Demo 常量）
    │
    ├─ UnifiedOrderRequestDemo.php / QueryOrderRequestDemo.php
    ├─ RefundRequestDemo.php / QueryRefundRequestDemo.php
    ├─ NotifyVerifyDemo.php
    ├─ v1/payment/create/        各支付场景（ALI_QR、ALI_LITE_H5、WX_PUB、WX_LITE、WX_LITE_H5、WX_LITE_JUMP、CUP_QR）
    ├─ v1/payment/division/      下单分账、延时分账确认 / 确认退款 / 可转余额 / 订单转账
    ├─ v1/quickpay/              绑卡申请 / 确认 / 解绑，支付申请 / 确认 / 短信校验
    ├─ v1/balancepay/            余额支付、余额查询
    ├─ v1/electronicaccount/     开户、业务开通 / 变更、用户查询、结算、提现
    └─ web/index.php             浏览器调试面板
```
