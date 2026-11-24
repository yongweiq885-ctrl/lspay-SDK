using System.Net;
using System.Net.Http;
using System.Text;
using LsPaySdk;

var tests = new List<(string Name, Func<Task> Run)>
{
    ("MD5 signing", TestSigning),
    ("request validation and mapping", TestRequest),
    ("form transport and typed response", TestTransport),
    ("cashier browser form", TestCashierForm),
    ("log redaction", TestRedaction),
    ("payment/refund/transfer notifications", TestNotifications),
    ("endpoint coverage", TestEndpoints),
    ("unified-order payment methods", TestUnifiedOrderPaymentMethods),
    ("flat transaction responses", TestFlatTransactionResponses),
    ("balance and settlement response shapes", TestResponseShapes),
    ("withdraw response compatibility", TestWithdrawResponses)
};

var failures = new List<string>();
foreach (var test in tests)
{
    try { await test.Run(); Console.WriteLine("[PASS] " + test.Name); }
    catch (Exception ex) { failures.Add(test.Name + ": " + ex); Console.WriteLine("[FAIL] " + test.Name + ": " + ex.Message); }
}
Console.WriteLine($"{tests.Count - failures.Count}/{tests.Count} tests passed");
if (failures.Count > 0) { foreach (var failure in failures) Console.Error.WriteLine(failure); return 1; }
return 0;

static Task TestSigning()
{
    var values = new Dictionary<string, object?> { ["mch_no"] = "M100", ["app_id"] = "A200", ["empty"] = "", ["amount"] = 1 };
    Equal("3EDC1AB5CD5B357D85B94AD2DA4D0A1B", LsPaySigner.Sign(values, "secret"));
    Equal(
        "CD9D772610FCC1396431811A0189F50E",
        LsPaySigner.Sign(
            new Dictionary<string, object?> { ["a"] = "1", ["b"] = "2" },
            "JT2025_DEMO_SECRET_KEY"));
    Equal(1234L, LsPaySigner.ToFen("12.34"));
    Equal("12.34", LsPaySigner.ToYuan(1234));
    return Task.CompletedTask;
}

static Task TestRequest()
{
    var extra = new UnifiedOrderChannelExtra().OpenId("openid-secret").PayData(PayDataType.WxApp);
    var request = new UnifiedOrderRequest
    {
        MchOrderNo = "ORDER100", WayCode = WayCode.WX_PUB, Amount = 100,
        Subject = "测试", Body = "测试商品", ClientIp = "127.0.0.1", ChannelExtra = extra
    };
    var parameters = request.ToParameters();
    Equal("WX_PUB", parameters["way_code"]);
    True(Convert.ToString(parameters["channel_extra"])!.Contains("openid-secret"));
    Throws<LsPayValidationException>(() => new QueryOrderRequest().ToParameters());
    Throws<LsPayValidationException>(() =>
    {
        request.ExtendInfo["amount"] = 2;
        request.ToParameters();
    });
    return Task.CompletedTask;
}

static async Task TestCashierForm()
{
    var handler = new StubHandler("""{"status":200}""");
    using var client = new LsPayClient(Options(), httpClient: new HttpClient(handler));
    var request = new UnifiedOrderRequest
    {
        MchOrderNo = "C1", WayCode = WayCode.ALI_QR, Amount = 1, Subject = "s", Body = "b",
        ChannelExtra = new UnifiedOrderChannelExtra().PayData(PayDataType.Cashier)
    };
    try
    {
        await client.PostAsync(request);
        throw new Exception("cashier 不应由服务端调用接口");
    }
    catch (LsPayValidationException ex)
    {
        True(ex.Message.Contains("BuildCashierForm"));
    }
    var form = client.BuildCashierForm(request);
    True(handler.Uri == null);
    Equal("POST", form.Method);
    True(form.Action.EndsWith("/gateway/unifiedorder"));
    var unsigned = form.Fields.Where(x => x.Key != "sign").ToDictionary(x => x.Key, x => (object?)x.Value);
    Equal(LsPaySigner.Sign(unsigned, Options().GetMerchant("default").Md5Key), form.Fields["sign"]);
    True(form.ToHtml().Contains("name=\"sign\"") && form.ToHtml().Contains(".submit()"));
    request.ChannelExtra = new UnifiedOrderChannelExtra().PayData(PayDataType.PayUrl);
    Throws<LsPayValidationException>(() => client.BuildCashierForm(request));
}

static async Task TestTransport()
{
    var handler = new StubHandler("""{"status":200,"msg":"成功","data":{"pay_order_id":"P100","mch_order_no":"O100","amount":100,"order_state":2,"trans_status":"S"}}""");
    var http = new HttpClient(handler);
    var options = Options();
    using var client = new LsPayClient(options, httpClient: http);
    var response = await client.PostAsync(new UnifiedOrderRequest
    {
        MchOrderNo = "O100", WayCode = WayCode.ALI_QR, Amount = 100,
        Subject = "subject", Body = "body",
        ChannelExtra = new UnifiedOrderChannelExtra().PayData(PayDataType.CodeImageUrl)
    });
    try
    {
        new UnifiedOrderRequest
        {
            MchOrderNo = "O101", WayCode = WayCode.ALI_QR, Amount = 100,
            Subject = "subject", Body = "body"
        }.ToParameters();
        throw new Exception("expected channel_extra 必填");
    }
    catch (LsPayValidationException ex)
    {
        Equal("channel_extra 必填", ex.Message);
    }
    True(response.IsAccepted && response.IsPaid);
    Equal("P100", response.PayOrderId);
    True(handler.Body!.Contains("notify_url=https%3A%2F%2Fmerchant.test%2Fnotify"));
    True(handler.Body.Contains("sign_type=MD5"));
    Equal("/gateway/unifiedorder", handler.Uri!.AbsolutePath);
}

static Task TestRedaction()
{
    var sanitized = System.Text.Json.JsonSerializer.Serialize(LogSanitizer.Sanitize(new Dictionary<string, object?>
    {
        ["sign"] = "1234567890", ["channel_extra"] = """{"openid":"openid-secret","pay_data_type":"wxapp"}"""
    }));
    True(!sanitized.Contains("1234567890") && !sanitized.Contains("openid-secret"));
    True(sanitized.Contains("pay_data_type"));
    return Task.CompletedTask;
}

static Task TestNotifications()
{
    var config = Options().GetMerchant();
    var payment = Signed(new Dictionary<string, string>
    {
        ["notify_type"]="payment.succeeded", ["event_type"]="payment.succeeded", ["pay_order_id"]="P1",
        ["mch_no"]="M100", ["app_id"]="A100", ["mch_order_no"]="O1", ["way_code"]="ALI_QR",
        ["amount"]="100", ["currency"]="cny", ["state"]="2", ["subject"]="s", ["body"]="b",
        ["add_time"]="1700000000", ["success_time"]="1700000001", ["req_time"]="1700000000"
    }, config.Md5Key);
    var pay = (PaymentNotify)LsPayNotify.Parse(payment, config);
    True(pay.IsSucceeded); pay.AssertOrder("O1", 100);

    var refund = Signed(new Dictionary<string, string>
    {
        ["notify_type"]="refund", ["event_type"]="refund.succeeded", ["pay_order_id"]="P1",
        ["mch_no"]="M100", ["app_id"]="A100", ["mch_refund_no"]="R1", ["way_code"]="ALI_QR",
        ["pay_amount"]="100", ["refund_amount"]="40", ["currency"]="cny", ["state"]="2", ["ext_param"]="",
        ["add_time"]="2026-01-01 01:02:03", ["success_time"]="2026-01-01 01:03:03", ["req_time"]="1700000000"
    }, config.Md5Key);
    var refNotify = (RefundNotify)LsPayNotify.Parse(refund, config);
    True(refNotify.IsSucceeded); refNotify.AssertRefund("R1", 40, payAmount: 100);

    var transfer = Signed(new Dictionary<string, string>
    {
        ["task_id"]="T1", ["mch_no"]="M100", ["app_id"]="A100", ["order_no"]="TO1",
        ["target_amount"]="10000", ["processed_amount"]="6500", ["remaining_amount"]="3500",
        ["status"]="PROCESSING", ["progress"]="65.0", ["account_no"]="ACC", ["account_name"]="NAME"
    }, config.Md5Key);
    var task = (DelayTransTransferNotify)LsPayNotify.Parse(transfer, config);
    Equal(6500L, task.ProcessedAmount); task.AssertTask("TO1", 10000);
    Throws<LsPaySignException>(() => { payment["amount"] = "101"; LsPayNotify.Parse(payment, config); });
    return Task.CompletedTask;
}

static Task TestEndpoints()
{
    Equal(22, Enum.GetValues<Endpoint>().Length);
    Equal("gateway/user/balance/withdraw/query", ProtocolValue.Of(Endpoint.ElectronicAccountBatchWithdrawQuery));
    var requests = new LsPayRequest[]
    {
        new UnifiedOrderRequest(), new QueryOrderRequest(), new RefundRequest(), new QueryRefundRequest(),
        new DelayTransConfirmRequest(), new DelayTransConfirmRefundRequest(), new DelayTransUnconfirmQueryRequest(),
        new DelayTransCreateTaskRequest(), new QuickPayBindApplyRequest(), new QuickPayBindConfirmRequest(),
        new QuickPayApplyRequest(), new QuickPayConfirmRequest(), new QuickPayUnbindRequest(), new QuickPaySmsCheckRequest(),
        new ElectronicAccountUserOpenRequest(), new ElectronicAccountBusinessOpenRequest(), new ElectronicAccountBusinessChangeRequest(),
        new ElectronicAccountUserQueryRequest(), new ElectronicAccountSettlementQueryRequest(), new ElectronicAccountBalanceWithdrawRequest(),
        new ElectronicAccountSingleWithdrawQueryRequest(), new ElectronicAccountBatchWithdrawQueryRequest(), new BalanceQueryRequest()
    };
    Equal(23, requests.Length); // unifiedorder has ordinary, balance and quick-pay typed variants
    return Task.CompletedTask;
}

static Task TestUnifiedOrderPaymentMethods()
{
    Equal(20, WayCodeCatalog.PaymentMethods.Count);
    True(WayCodeCatalog.IsPaymentMethod(WayCode.BANK_FAST));
    True(WayCodeCatalog.IsPaymentMethod(WayCode.BANK_FAST_JUMP));
    foreach (var wayCode in new[]
    {
        WayCode.APPLY, WayCode.BALANCE_PAY, WayCode.BALANCE_QUERY, WayCode.SETTLE_QUERY,
        WayCode.BALANCE_WITHDRAW, WayCode.WITHDRAW_SINGLE_QUERY, WayCode.WITHDRAW_QUERY
    })
    {
        True(!WayCodeCatalog.IsPaymentMethod(wayCode));
        Throws<LsPayValidationException>(() => new UnifiedOrderRequest
        {
            MchOrderNo = "ORD-" + wayCode,
            WayCode = wayCode,
            Amount = 100,
            Subject = "subject",
            Body = "body"
        }.ToParameters());
    }

    var balanceExtra = new BalancePayChannelExtra().TransOut("ACC-OUT");
    balanceExtra.AddReceiver(new DivisionReceiver("ACC-IN", 100));
    var balance = new BalancePayRequest
    {
        MchOrderNo = "BAL-100",
        Amount = 100,
        Subject = "余额支付",
        Body = "余额支付",
        ChannelExtra = balanceExtra
    }.ToParameters();
    Equal("BALANCE_PAY", balance["way_code"]);

    var quick = new QuickPayApplyRequest
    {
        MchOrderNo = "FAST-100",
        Amount = 100,
        Subject = "快捷支付",
        Body = "快捷支付",
        ChannelExtra = new UnifiedOrderChannelExtra()
            .ChannelUser("USER-1")
            .ChannelCardId("CARD-1")
    }.ToParameters();
    Equal("BANK_FAST", quick["way_code"]);
    return Task.CompletedTask;
}

static Task TestFlatTransactionResponses()
{
    var quickSuccess = LsPayResponse.Parse<QuickPayBindApplyResponse>(
        """{"trans_status":"S","trans_id":"T100","order_id":"O100","resp_desc":"成功"}""");
    True(!quickSuccess.IsError && quickSuccess.IsAccepted && quickSuccess.IsSucceeded);
    Equal("T100", quickSuccess.TransId);

    var quickProcessing = LsPayResponse.Parse<QuickPayConfirmResponse>(
        """{"trans_status":"P","err_msg":"处理中"}""");
    True(!quickProcessing.IsError && quickProcessing.IsProcessing);
    var quickFailed = LsPayResponse.Parse<QuickPayConfirmResponse>("""{"trans_status":"F","err_msg":"失败"}""");
    True(quickFailed.IsError && !quickFailed.IsSucceeded);
    var wrappedQuickFailure = LsPayResponse.Parse<QuickPayConfirmResponse>(
        """{"status":400,"msg":"拒绝","data":{"trans_status":"S"}}""");
    True(wrappedQuickFailure.IsError && !wrappedQuickFailure.IsSucceeded);

    var accountSuccess = LsPayResponse.Parse<ElectronicAccountUserOpenResponse>(
        """{"trans_status":"S","account_no":"ACC100","resp_desc":"成功"}""");
    True(!accountSuccess.IsError && accountSuccess.IsSucceeded);
    Equal("ACC100", accountSuccess.AccountNo);
    var accountProcessing = LsPayResponse.Parse<ElectronicAccountBusinessOpenResponse>(
        """{"trans_status":"P","account_no":"ACC100"}""");
    True(!accountProcessing.IsError && accountProcessing.IsProcessing);
    var accountFailure = LsPayResponse.Parse<ElectronicAccountBusinessChangeResponse>(
        """{"trans_status":"F","resp_desc":"失败"}""");
    True(accountFailure.IsError && !accountFailure.IsSucceeded);
    return Task.CompletedTask;
}

static Task TestResponseShapes()
{
    var balance = LsPayResponse.Parse<BalanceQueryResponse>(
        """{"acct_type":"01","acct_stat":"N","balance_amt":"12.34","avl_balance":"10.00","frz_bal":"2.34","dm_amt":"1.00"}""");
    True(!balance.IsError && balance.IsNormal);
    Equal("01", balance.AccountType);
    Equal(12.34m, balance.BalanceAmount);

    var single = LsPayResponse.Parse<ElectronicAccountSettlementQueryResponse>(
        """{"status":200,"data":{"trans_id":"ONE","trans_date":"20261015","trans_amt":"12.34"}}""");
    Equal(1, single.Records.Count);
    Equal("ONE", single.TransId);

    var array = LsPayResponse.Parse<ElectronicAccountSettlementQueryResponse>(
        """{"status":200,"data":[{"trans_id":"A"},{"trans_id":"B"}]}""");
    Equal(2, array.Records.Count);
    Equal("A", array.TransId);

    foreach (var key in new[] { "records", "list", "rows" })
    {
        var paged = LsPayResponse.Parse<ElectronicAccountSettlementQueryResponse>(
            "{\"status\":200,\"data\":{\"" + key + "\":[{\"trans_id\":\"" + key + "-1\"}]}}");
        Equal(1, paged.Records.Count);
        Equal(key + "-1", paged.TransId);
    }
    var task = LsPayResponse.Parse<DelayTransCreateTaskResponse>(
        """{"status":200,"data":{"status":"PENDING","selected_orders":2}}""");
    True(task.IsCreated);
    Equal(2, task.SelectedOrders);
    return Task.CompletedTask;
}

static Task TestWithdrawResponses()
{
    var accepted = LsPayResponse.Parse<ElectronicAccountBalanceWithdrawResponse>(
        """{"status":200,"msg":"success","data":{"err_msg":"受理成功","msg":"兼容消息","resp_desc":"兼容描述","status":"success"}}""");
    True(accepted.IsAccepted);
    Equal("受理成功", accepted.AcceptanceMessage);
    True(LsPayResponse.Parse<ElectronicAccountBalanceWithdrawResponse>(
        """{"status":200,"data":{"status":"succeess"}}""").IsAccepted);
    Equal("兼容消息", LsPayResponse.Parse<ElectronicAccountBalanceWithdrawResponse>(
        """{"status":200,"data":{"msg":"兼容消息","status":"success"}}""").AcceptanceMessage);
    Equal("兼容描述", LsPayResponse.Parse<ElectronicAccountBalanceWithdrawResponse>(
        """{"status":200,"data":{"resp_desc":"兼容描述","status":"success"}}""").AcceptanceMessage);

    var single = LsPayResponse.Parse<ElectronicAccountSingleWithdrawQueryResponse>(
        """{"status":200,"data":{"withdraw_date":"20261015","order_id":"W1","cash_amt":"10.00","fee_amt":"0.10","trans_status":"succeeded","trans_desc":"完成","status":"succeess","err_msg":"OK"}}""");
    True(single.IsSucceeded && !single.IsPending && !single.IsFailed);
    True(single.IsChannelSuccessful);
    Equal("succeess", single.ChannelStatus);
    Equal("OK", single.ChannelMessage);

    var pending = LsPayResponse.Parse<ElectronicAccountSingleWithdrawQueryResponse>(
        """{"status":200,"data":{"trans_status":"pending","status":"success"}}""");
    True(pending.IsPending && pending.IsChannelSuccessful);

    var batch = LsPayResponse.Parse<ElectronicAccountBatchWithdrawQueryResponse>(
        """{"status":200,"data":{"count":1,"page_size":20,"page_num":1,"status":"succeess","records":[{"order_id":"WRONG"}],"data":[{"order_id":"RIGHT","trans_status":"succeeded","cash_amt":"10.00"}]}}""");
    Equal(1, batch.Records.Count);
    Equal("RIGHT", batch.OrderId);
    True(batch.IsFirstRecordSucceeded && batch.IsChannelSuccessful);
    Equal(1, batch.Count);
    return Task.CompletedTask;
}

static LsPayOptions Options() => new LsPayOptions().AddMerchant("default", new MerchantConfig
{
    MchNo = "M100", AppId = "A100", Md5Key = "secret", NotifyUrl = "https://merchant.test/notify"
});

static Dictionary<string, string> Signed(Dictionary<string, string> data, string key)
{
    data["sign"] = LsPaySigner.Sign(data.Select(x => new KeyValuePair<string, object?>(x.Key, x.Value)), key);
    return data;
}

static void True(bool value) { if (!value) throw new Exception("assert true failed"); }
static void Equal<T>(T expected, T actual) { if (!EqualityComparer<T>.Default.Equals(expected, actual)) throw new Exception($"expected={expected}, actual={actual}"); }
static void Throws<T>(Action action) where T : Exception { try { action(); } catch (T) { return; } throw new Exception("expected " + typeof(T).Name); }

sealed class StubHandler : HttpMessageHandler
{
    private readonly string _response;
    public string? Body { get; private set; }
    public Uri? Uri { get; private set; }
    public StubHandler(string response) { _response = response; }
    protected override async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
    {
        Uri = request.RequestUri;
        Body = await request.Content!.ReadAsStringAsync(cancellationToken);
        return new HttpResponseMessage(HttpStatusCode.OK) { Content = new StringContent(_response, Encoding.UTF8, "application/json") };
    }
}
