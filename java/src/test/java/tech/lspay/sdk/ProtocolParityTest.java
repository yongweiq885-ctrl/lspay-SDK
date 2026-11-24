package tech.lspay.sdk;

import org.junit.Before;
import org.junit.Test;
import tech.lspay.sdk.config.MerConfig;
import tech.lspay.sdk.core.CashierForm;
import tech.lspay.sdk.core.LsPay;
import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.core.LsPayTools;
import tech.lspay.sdk.enums.EndpointEnum;
import tech.lspay.sdk.enums.WayCodeEnum;
import tech.lspay.sdk.exceptions.LsPayConfigException;
import tech.lspay.sdk.notify.BaseNotify;
import tech.lspay.sdk.notify.LsPayNotify;
import tech.lspay.sdk.notify.PaymentNotify;
import tech.lspay.sdk.request.*;
import tech.lspay.sdk.response.*;

import java.util.LinkedHashMap;
import java.util.Map;

import static org.junit.Assert.*;

public class ProtocolParityTest {
    private MerConfig config;

    @Before public void setup() {
        LsPay.clear();
        config = new MerConfig();
        config.mchNo = "M100";
        config.appId = "A100";
        config.md5Key = "secret";
        LsPay.init(config);
    }

    @Test public void configValidationMatchesOtherLanguages() {
        MerConfig padded = merConfig("  M200  ", " A200 ");
        padded.notifyUrl = " https://example.com/notify ";
        LsPay.init("padded", padded);
        assertEquals("M200", LsPay.getConfig("padded").mchNo);
        assertEquals("A200", LsPay.getConfig("padded").appId);
        assertEquals("https://example.com/notify", LsPay.getConfig("padded").notifyUrl);

        assertConfigRejected(merConfig("   ", "A200"), "mch_no 必填");
        assertConfigRejected(merConfig(repeat('M', 31), "A200"), "mch_no 长度不能超过 30");
        assertConfigRejected(merConfig("M200", repeat('A', 25)), "app_id 长度不能超过 24");
        MerConfig badUrl = merConfig("M200", "A200");
        badUrl.returnUrl = "ftp://example.com/return";
        assertConfigRejected(badUrl, "return_url 必须是长度不超过 128 的 HTTP(S) URL");
        MerConfig longUrl = merConfig("M200", "A200");
        longUrl.notifyUrl = "https://example.com/" + repeat('a', 109);
        assertConfigRejected(longUrl, "notify_url 必须是长度不超过 128 的 HTTP(S) URL");
    }

    private static MerConfig merConfig(String mchNo, String appId) {
        MerConfig cfg = new MerConfig();
        cfg.mchNo = mchNo;
        cfg.appId = appId;
        cfg.md5Key = "secret";
        return cfg;
    }

    private static void assertConfigRejected(MerConfig cfg, String message) {
        try {
            LsPay.init("invalid", cfg);
            fail("expected config error: " + message);
        } catch (LsPayConfigException e) {
            assertEquals(message, e.getMessage());
        }
        assertFalse(LsPay.hasConfig("invalid"));
    }

    private static String repeat(char c, int n) {
        StringBuilder sb = new StringBuilder(n);
        for (int i = 0; i < n; i++) sb.append(c);
        return sb.toString();
    }

    @Test public void cashierUsesBrowserForm() {
        UnifiedOrderRequest req = new UnifiedOrderRequest()
                .setMchOrderNo("C1").setWayCode(WayCodeEnum.ALI_QR)
                .setAmount(1).setSubject("s").setBody("b")
                .setChannelExtra("{\"pay_data_type\":\"cashier\"}");
        try {
            LsPayClient.execute(req);
            fail("cashier must not be posted by server");
        } catch (IllegalArgumentException expected) {
            assertTrue(expected.getMessage().contains("buildCashierForm"));
        }

        CashierForm form = LsPayClient.buildCashierForm(req);
        assertEquals("POST", form.getMethod());
        assertTrue(form.getAction().endsWith(EndpointEnum.UNIFIED_ORDER));
        Map<String, Object> unsigned = new LinkedHashMap<>(form.getFields());
        String sign = (String) unsigned.remove("sign");
        assertEquals(LsPayTools.sign(unsigned, config.md5Key), sign);
        assertEquals("M100", form.getFields().get("mch_no"));
        assertTrue(form.toHtml().contains("name=\"sign\"") && form.toHtml().contains(".submit()"));

        try {
            LsPayClient.buildCashierForm(req.setChannelExtra("{\"pay_data_type\":\"pay_url\"}"));
            fail("non-cashier must not build a cashier form");
        } catch (IllegalArgumentException expected) {}
    }

    @Test public void signingMatchesCrossLanguageFixture() {
        Map<String, Object> values = new LinkedHashMap<>();
        values.put("a", "1");
        values.put("b", "2");
        assertEquals(
                "CD9D772610FCC1396431811A0189F50E",
                LsPayTools.sign(values, "JT2025_DEMO_SECRET_KEY")
        );
    }

    @Test public void endpointAndMapRoutingCoverAllFamilies() {
        assertEquals(22, EndpointEnum.all().size());
        assertEquals(20, WayCodeEnum.paymentMethods().size());
        assertEquals("gateway/delaytrans/confirm", EndpointEnum.DELAY_TRANS_CONFIRM);
        assertEquals("gateway/quickpay/buckleapply", EndpointEnum.QUICK_PAY_BIND_APPLY);
        assertEquals("gateway/user/personapply", EndpointEnum.ELECTRONIC_ACCOUNT_USER_OPEN);
        assertEquals("gateway/user/balance/withdraw/query", EndpointEnum.ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY);
        assertTrue(RequestFactory.supports(EndpointEnum.ELECTRONIC_ACCOUNT_BUSINESS_CHANGE));
        assertRoute(EndpointEnum.QUERY_ORDER, QueryOrderRequest.class);
        assertRoute(EndpointEnum.REFUND, RefundRequest.class);
        assertRoute(EndpointEnum.QUERY_REFUND, QueryRefundRequest.class);
        assertRoute(EndpointEnum.DELAY_TRANS_CONFIRM, DelayTransConfirmRequest.class);
        assertRoute(EndpointEnum.DELAY_TRANS_CONFIRM_REFUND, DelayTransConfirmRefundRequest.class);
        assertRoute(EndpointEnum.DELAY_TRANS_UNCONFIRM_QUERY, DelayTransUnconfirmQueryRequest.class);
        assertRoute(EndpointEnum.DELAY_TRANS_CREATE_TASK, DelayTransCreateTaskRequest.class);
        assertRoute(EndpointEnum.QUICK_PAY_BIND_APPLY, QuickPayBindApplyRequest.class);
        assertRoute(EndpointEnum.QUICK_PAY_BIND_CONFIRM, QuickPayBindConfirmRequest.class);
        assertRoute(EndpointEnum.QUICK_PAY_CONFIRM, QuickPayConfirmRequest.class);
        assertRoute(EndpointEnum.QUICK_PAY_UNBIND, QuickPayUnbindRequest.class);
        assertRoute(EndpointEnum.QUICK_PAY_SMS_CHECK, QuickPaySmsCheckRequest.class);
        assertRoute(EndpointEnum.ELECTRONIC_ACCOUNT_USER_OPEN, ElectronicAccountUserOpenRequest.class);
        assertRoute(EndpointEnum.ELECTRONIC_ACCOUNT_BUSINESS_OPEN, ElectronicAccountBusinessOpenRequest.class);
        assertRoute(EndpointEnum.ELECTRONIC_ACCOUNT_BUSINESS_CHANGE, ElectronicAccountBusinessChangeRequest.class);
        assertRoute(EndpointEnum.ELECTRONIC_ACCOUNT_USER_QUERY, ElectronicAccountUserQueryRequest.class);
        assertRoute(EndpointEnum.ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY, ElectronicAccountSettlementQueryRequest.class);
        assertRoute(EndpointEnum.ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW, ElectronicAccountBalanceWithdrawRequest.class);
        assertRoute(EndpointEnum.ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY, ElectronicAccountSingleWithdrawQueryRequest.class);
        assertRoute(EndpointEnum.ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY, ElectronicAccountBatchWithdrawQueryRequest.class);

        Map<String, Object> params = new LinkedHashMap<>();
        params.put("way_code", WayCodeEnum.BALANCE_QUERY);
        params.put("order_id", "Q1");
        params.put("account_no", "AC1");
        params.put("future_field", "kept");
        BaseRequest request = RequestFactory.fromMap(EndpointEnum.BALANCE_QUERY, params);
        assertTrue(request instanceof BalanceQueryRequest);
        request.validate();
        assertEquals("kept", request.toMap().get("future_field"));
    }

    @Test public void fixedWayCodesAndNestedBuildersAreSerialized() {
        try {
            new QuickPayBindApplyRequest().setWayCode(WayCodeEnum.ALI_QR);
            fail("fixed way_code must reject changes");
        } catch (IllegalArgumentException expected) {}

        BalancePayChannelExtra extra = new BalancePayChannelExtra()
                .setTransOut("OUT-1");
        extra.addDivisionReceiver(new DivisionReceiver("IN-1").setDivAmt(10));
        BalancePayRequest request = new BalancePayRequest();
        request.setMchOrderNo("B1").setAmount(10).setSubject("s").setBody("b");
        request.setChannelExtra(extra);
        request.validate();
        assertTrue(request.channelExtra.contains("\"trans_out\":\"OUT-1\""));
        assertTrue(request.channelExtra.contains("\"div_amt\":10"));

        UnifiedOrderRequest invalid = new UnifiedOrderRequest()
                .setMchOrderNo("U1").setWayCode(WayCodeEnum.APPLY)
                .setAmount(1).setSubject("s").setBody("b");
        try {
            invalid.validate();
            fail("ordinary unified order must reject platform business way_code");
        } catch (IllegalArgumentException expected) {}

        UnifiedOrderRequest missingExtra = new UnifiedOrderRequest()
                .setMchOrderNo("U2").setWayCode(WayCodeEnum.ALI_QR)
                .setAmount(1).setSubject("s").setBody("b");
        try {
            missingExtra.validate();
            fail("unified order must require channel_extra");
        } catch (IllegalArgumentException expected) {
            assertEquals("channel_extra 必填", expected.getMessage());
        }
    }

    @Test public void electronicAccountBuildersAndValidationWork() {
        ElectronicAccountOpenChannelExtra extra = new ElectronicAccountOpenChannelExtra()
                .setUserInfo(new ElectronicAccountUserInfo()
                        .setCertType("00").setName("张三").setCertNo("ID")
                        .setMobileNo("13800000000"));
        ElectronicAccountUserOpenRequest request = new ElectronicAccountUserOpenRequest()
                .setOrderId("U1").setAccountName("张三").setAccountType(1)
                .setRelationType("USER").setAccountAlias("alias")
                .setDivisionProfit("0.1").setChannelExtra(extra);
        request.validate();
        assertEquals(WayCodeEnum.APPLY, request.wayCode);
    }

    @Test public void notificationUsesRawFieldsForSignatureAndChecksMerchant() {
        Map<String, Object> notify = new LinkedHashMap<>();
        notify.put("notify_type", "payment");
        notify.put("event_type", "payment.succeeded");
        notify.put("pay_order_id", "P1");
        notify.put("mch_no", config.mchNo);
        notify.put("app_id", config.appId);
        notify.put("mch_order_no", "M1");
        notify.put("way_code", WayCodeEnum.ALI_QR);
        notify.put("amount", "100");
        notify.put("currency", "cny");
        notify.put("state", "2");
        notify.put("subject", "subject");
        notify.put("body", "body");
        notify.put("add_time", "1700000000");
        notify.put("future_signed_field", "future");
        notify.put("sign", LsPayTools.sign(notify, config.md5Key));

        BaseNotify parsed = LsPayNotify.parse(notify);
        assertTrue(parsed instanceof PaymentNotify);
        assertTrue(((PaymentNotify) parsed).isSucceeded());
        assertEquals("future", parsed.getRaw().get("future_signed_field"));
    }

    @Test public void sensitiveLoggingMasksTopLevelAndChannelExtra() {
        Map<String, Object> values = new LinkedHashMap<>();
        values.put("sign", "0123456789");
        values.put("account_no", "6222020202020202");
        values.put("channel_extra", "{\"card_no\":\"6222333344445555\",\"safe\":\"ok\"}");
        String logged = String.valueOf(LsPay.sanitizeForLog(values));
        assertFalse(logged.contains("6222020202020202"));
        assertFalse(logged.contains("6222333344445555"));
        assertTrue(logged.contains("safe"));
    }

    @Test public void officialFlatElectronicAndBalanceResponsesAreSupported() {
        ElectronicAccountUserOpenResponse success = new ElectronicAccountUserOpenResponse(
                "{\"trans_status\":\"S\",\"account_no\":\"AC1\"}");
        assertFalse(success.isError());
        assertTrue(success.isSuccessful());

        ElectronicAccountUserOpenResponse processing = new ElectronicAccountUserOpenResponse(
                "{\"trans_status\":\"P\"}");
        assertFalse(processing.isError());
        assertTrue(processing.isProcessing());

        ElectronicAccountUserOpenResponse failed = new ElectronicAccountUserOpenResponse(
                "{\"trans_status\":\"F\"}");
        assertTrue(failed.isError());
        assertFalse(failed.isSuccessful());

        BalanceQueryResponse balance = new BalanceQueryResponse(
                "{\"acct_type\":\"01\",\"acct_stat\":\"N\",\"balance_amt\":\"100.25\","
                        + "\"avl_balance\":\"80.10\",\"frz_bal\":\"20.15\",\"dm_amt\":\"0.00\"}");
        assertFalse(balance.isError());
        assertEquals("01", balance.getAccountType());
        assertEquals("100.25", balance.getBalanceAmount());
        assertEquals("80.10", balance.getAvailableBalance());
        assertEquals("20.15", balance.getFrozenBalance());
        assertEquals("0.00", balance.getHistoricalBalance());
        assertTrue(balance.isNormal());
    }

    @Test public void flatElectronicGettersAndQuickPayGatewayFailuresAreCorrect() {
        ElectronicAccountUserOpenResponse userOpen = new ElectronicAccountUserOpenResponse(
                "{\"trans_status\":\"S\",\"account_no\":\"U1\",\"resp_desc\":\"opened\"}");
        assertEquals("U1", userOpen.getAccountNo());
        assertEquals("opened", userOpen.getRespDesc());

        ElectronicAccountBusinessOpenResponse businessOpen = new ElectronicAccountBusinessOpenResponse(
                "{\"trans_status\":\"S\",\"account_no\":\"B1\",\"resp_desc\":\"opened\"}");
        assertEquals("B1", businessOpen.getAccountNo());
        assertEquals("opened", businessOpen.getRespDesc());

        ElectronicAccountBusinessChangeResponse businessChange = new ElectronicAccountBusinessChangeResponse(
                "{\"trans_status\":\"S\",\"account_no\":\"B2\",\"resp_desc\":\"changed\"}");
        assertEquals("B2", businessChange.getAccountNo());
        assertEquals("changed", businessChange.getRespDesc());

        ElectronicAccountUserQueryResponse userQuery = new ElectronicAccountUserQueryResponse(
                "{\"trans_status\":\"S\",\"account_no\":\"Q1\",\"account_name\":\"张三\","
                        + "\"channel_status\":\"1\",\"resp_desc\":\"ok\",\"channel_extra\":\"{}\"}");
        assertEquals("Q1", userQuery.getAccountNo());
        assertEquals("张三", userQuery.getAccountName());
        assertEquals("1", userQuery.getChannelStatus());
        assertEquals("ok", userQuery.getRespDesc());
        assertEquals("{}", userQuery.getChannelExtra());
        assertTrue(userQuery.isOnboarded());
        ElectronicAccountUserQueryResponse numericFailure = new ElectronicAccountUserQueryResponse(
                "{\"status\":200,\"data\":{\"trans_status\":\"S\",\"channel_status\":2,"
                        + "\"account_type\":1,\"relation_type\":\"USER\",\"division_profit\":\"0.0100\","
                        + "\"channel_extra\":{\"settle_cycle\":\"D1\"}}}");
        assertEquals("2", numericFailure.getChannelStatus());
        assertEquals("1", numericFailure.getAccountType());
        assertEquals("USER", numericFailure.getRelationType());
        assertEquals("0.0100", numericFailure.getDivisionProfit());
        assertFalse(numericFailure.isOnboarded());
        assertTrue(numericFailure.getChannelExtra().contains("settle_cycle"));

        QuickPayConfirmResponse gatewayFailure = new QuickPayConfirmResponse(
                "{\"status\":400,\"msg\":\"failed\",\"data\":{\"trans_status\":\"S\"}}");
        assertTrue(gatewayFailure.isError());
        assertFalse(gatewayFailure.isAccepted());
        assertFalse(gatewayFailure.isSuccessful());
        assertFalse(gatewayFailure.isProcessing());
        QuickPayConfirmResponse processingFailure = new QuickPayConfirmResponse(
                "{\"status\":400,\"data\":{\"trans_status\":\"P\"}}");
        assertFalse(processingFailure.isAccepted());
        assertFalse(processingFailure.isProcessing());
    }

    @Test public void settlementAcceptsOfficialRecordShapes() {
        assertEquals("ONE", new ElectronicAccountSettlementQueryResponse(
                "{\"status\":200,\"data\":{\"trans_id\":\"ONE\"}}").getTransId());
        assertEquals(2, new ElectronicAccountSettlementQueryResponse(
                "{\"status\":200,\"data\":[{\"trans_id\":\"A\"},{\"trans_id\":\"B\"}]}").getRecords().size());
        for (String key : new String[]{"records", "list", "rows"}) {
            ElectronicAccountSettlementQueryResponse response =
                    new ElectronicAccountSettlementQueryResponse(
                            "{\"status\":200,\"data\":{\"" + key + "\":[{\"trans_id\":\"" + key + "\"}]}}");
            assertEquals(key, response.getTransId());
        }
    }

    @Test public void officialWithdrawStructuresPreserveProtocolValues() {
        assertTrue(new ElectronicAccountBalanceWithdrawResponse(
                "{\"status\":200,\"data\":{\"status\":\"succeess\"}}").isAccepted());
        ElectronicAccountBatchWithdrawQueryResponse batch =
                new ElectronicAccountBatchWithdrawQueryResponse(
                        "{\"status\":200,\"data\":{\"count\":1,\"page_size\":15,\"page_num\":1,"
                                + "\"status\":\"succeess\",\"data\":[{\"batch_trans_type\":\"ENCHASHMENT\","
                                + "\"cash_amt\":\"11105.44\",\"fee_amt\":\"5.56\",\"fee_flag\":\"2\","
                                + "\"org_req_date\":\"20250422\",\"trans_status\":\"failed\"}]}}");
        assertEquals(1, batch.getRecords().size());
        assertEquals("ENCHASHMENT", batch.getBatchTransType());
        assertEquals("2", batch.getFeeFlag());
        assertEquals("20250422", batch.getOriginalRequestDate());
        assertTrue(batch.isChannelSuccessful());
        assertTrue(batch.isFirstRecordFailed());

        ElectronicAccountSingleWithdrawQueryResponse single =
                new ElectronicAccountSingleWithdrawQueryResponse(
                        "{\"status\":200,\"data\":{\"cash_amt\":\"1.00\",\"fee_amt\":\"0.00\","
                                + "\"trans_status\":\"succeeded\",\"status\":\"success\",\"err_msg\":\"ok\"}}");
        assertEquals("1.00", single.getCashAmount());
        assertEquals("0.00", single.getFeeAmount());
        assertEquals("ok", single.getChannelMessage());
        assertTrue(single.isSucceeded());
        assertTrue(single.isChannelSuccessful());
    }

    @Test public void historicalAliasesAndTaskMetadataAreSupported() {
        RefundResponse refund = new RefundResponse(
                "{\"status\":200,\"data\":{\"order_refund_Id\":\"R1\",\"channel_rrder_no\":\"C1\"}}");
        assertEquals("R1", refund.getRefundOrderId());
        assertEquals("C1", refund.getChannelOrderNo());
        assertTrue(refund.isAccepted());
        assertFalse(refund.isRefunded());
        assertTrue(new RefundResponse(
                "{\"status\":200,\"data\":{\"state\":2}}").isRefunded());

        DelayTransConfirmResponse confirm = new DelayTransConfirmResponse(
                "{\"status\":200,\"data\":{\"trans_status\":\"S\"}}");
        assertTrue(confirm.isAccepted());
        assertTrue(confirm.isSuccessful());
        assertFalse(new DelayTransConfirmResponse(
                "{\"status\":400,\"data\":{\"trans_status\":\"S\"}}").isSuccessful());

        DelayTransConfirmRefundResponse confirmRefund = new DelayTransConfirmRefundResponse(
                "{\"status\":200,\"data\":{\"trans_status\":\"S\"}}");
        assertTrue(confirmRefund.isAccepted());
        assertTrue(confirmRefund.isSuccessful());
        assertFalse(new DelayTransConfirmRefundResponse(
                "{\"status\":400,\"data\":{\"trans_status\":\"S\"}}").isSuccessful());

        DelayTransCreateTaskResponse task = new DelayTransCreateTaskResponse(
                "{\"status\":200,\"data\":{\"status\":\"PENDING\",\"selected_orders\":3,"
                        + "\"first_order_time\":\"2026-01-01 00:00:00\","
                        + "\"last_order_time\":\"2026-01-02 00:00:00\"}}");
        assertEquals(Long.valueOf(3), task.getSelectedOrders());
        assertEquals("2026-01-01 00:00:00", task.getFirstOrderTime());
        assertEquals("2026-01-02 00:00:00", task.getLastOrderTime());
        assertTrue(task.isCreated());
        assertFalse(new DelayTransCreateTaskResponse(
                "{\"status\":400,\"data\":{\"status\":\"PENDING\"}}").isCreated());
    }

    private static void assertRoute(String endpoint, Class<? extends BaseRequest> expected) {
        assertEquals(expected, RequestFactory.fromMap(endpoint, new LinkedHashMap<String, Object>()).getClass());
    }
}
