import json
import unittest
from unittest.mock import patch

from lspay import (
    BalancePayRequest,
    BalanceQueryRequest,
    BalanceQueryResponse,
    DelayTransConfirmRequest,
    DelayTransCreateTaskRequest,
    DelayTransCreateTaskResponse,
    DelayTransTransferNotify,
    ElectronicAccountBalanceWithdrawRequest,
    ElectronicAccountBalanceWithdrawResponse,
    ElectronicAccountBatchWithdrawQueryRequest,
    ElectronicAccountBatchWithdrawQueryResponse,
    ElectronicAccountBusinessOpenRequest,
    ElectronicAccountSettlementQueryRequest,
    ElectronicAccountSettlementQueryResponse,
    ElectronicAccountSingleWithdrawQueryRequest,
    ElectronicAccountSingleWithdrawQueryResponse,
    ElectronicAccountUserOpenRequest,
    ElectronicAccountUserQueryRequest,
    Endpoint,
    LsPay,
    LsPayClient,
    LsPayNotify,
    LsPaySignException,
    LsPayTools,
    LsPayValidationException,
    MerConfig,
    PaymentNotify,
    QueryOrderRequest,
    QuickPayApplyRequest,
    QuickPayBindApplyRequest,
    QuickPayBindApplyResponse,
    QuickPayBindConfirmRequest,
    QuickPayConfirmRequest,
    QuickPaySmsCheckRequest,
    QuickPayUnbindRequest,
    RefundNotify,
    RefundRequest,
    UnifiedOrderRequest,
    WayCode,
)


class FakeResponse:
    def __init__(self, body):
        self.body = body

    def __enter__(self):
        return self

    def __exit__(self, *args):
        return False

    def read(self):
        return self.body.encode("utf-8")


class ProtocolTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        LsPay.clear()
        LsPay.init(MerConfig(
            mch_no="TEST_MERCHANT",
            app_id="TEST_APP",
            md5_key="test-secret",
            notify_url="https://merchant.test/notify",
        ))

    def test_all_endpoints_registered(self):
        self.assertEqual(22, len(Endpoint.all()))
        self.assertEqual(20, len(WayCode.payment_methods()))

    def test_cashier_uses_browser_form(self):
        request = UnifiedOrderRequest(
            mch_order_no="C1", way_code=WayCode.ALI_QR, amount=1,
            subject="商品", body="描述", channel_extra={"pay_data_type": "cashier"},
        )
        with patch("urllib.request.urlopen") as urlopen:
            with self.assertRaisesRegex(LsPayValidationException, "build_cashier_form"):
                LsPayClient.execute(request)
            form = LsPayClient.build_cashier_form(request)
            urlopen.assert_not_called()
        self.assertEqual("POST", form.method)
        self.assertTrue(form.action.endswith(Endpoint.UNIFIED_ORDER))
        fields = dict(form.fields)
        sign = fields.pop("sign")
        self.assertEqual(LsPayTools.sign(fields, "test-secret"), sign)
        self.assertEqual('{"pay_data_type":"cashier"}', form.fields["channel_extra"])
        self.assertIn('name="sign"', form.to_html())
        self.assertIn(".submit()", form.to_html())
        with self.assertRaises(LsPayValidationException):
            LsPayClient.build_cashier_form(UnifiedOrderRequest(
                mch_order_no="C2", way_code=WayCode.ALI_QR, amount=1,
                subject="商品", body="描述", channel_extra={"pay_data_type": "pay_url"},
            ))

    def test_signing_matches_cross_language_fixture(self):
        self.assertEqual(
            "CD9D772610FCC1396431811A0189F50E",
            LsPayTools.sign({"a": "1", "b": "2"}, "JT2025_DEMO_SECRET_KEY"),
        )

    def test_unified_order_validation_and_builder(self):
        request = UnifiedOrderRequest(
            mch_order_no="ORDER1", way_code=WayCode.WX_PUB, amount=100,
            subject="商品", body="描述", channel_extra={"openid": "secret"},
        )
        request.validate()
        self.assertEqual('{"openid":"secret"}', request.channel_extra)
        with self.assertRaises(LsPayValidationException):
            UnifiedOrderRequest(
                mch_order_no="ORDER2", way_code=WayCode.WX_PUB, amount=100,
                subject="商品", body="描述",
            ).validate()
        with self.assertRaises(LsPayValidationException):
            UnifiedOrderRequest(
                mch_order_no="ORDER3", way_code=WayCode.BALANCE_QUERY, amount=100,
                subject="商品", body="描述",
            ).validate()

    def test_balance_pay_and_query_fixed_codes(self):
        BalancePayRequest(
            mch_order_no="B1", amount=100, subject="余额", body="余额",
            channel_extra={
                "trans_out": "A",
                "div_list": [{"trans_in": "B", "div_amt": 100}],
            },
        ).validate()
        BalanceQueryRequest(order_id="Q1", account_no="A1").validate()
        with self.assertRaises(LsPayValidationException):
            BalanceQueryRequest(
                order_id="Q1", account_no="A1", way_code="OTHER"
            ).validate()

    def test_payment_four_interfaces(self):
        QueryOrderRequest(mch_order_no="O1").validate()
        RefundRequest(
            mch_refund_no="R1", mch_order_no="O1", refund_amount=1,
            refund_reason="用户申请",
        ).validate()
        with self.assertRaises(LsPayValidationException):
            QueryOrderRequest().validate()

    def test_delay_trans_four_interfaces(self):
        DelayTransConfirmRequest(mch_order_no="O1", order_id="C1").validate()
        DelayTransCreateTaskRequest(
            account_no="A1", account_name="张三", order_no="T1", amount=100
        ).validate()

    def test_quick_pay_six_interfaces(self):
        QuickPayApplyRequest(
            mch_order_no="P1", amount=1, subject="快捷", body="快捷",
            channel_extra={"channel_user": "U", "channel_card_id": "C"},
        ).validate()
        bind = {
            "mch_user_id": "U", "card_id": "6222", "card_name": "张三",
            "cert_id": "ID", "cert_validity_type": "0",
            "cert_begin_date": "20200101", "cert_end_date": "20300101",
            "card_mp": "13800000000", "dc_type": "D",
        }
        QuickPayBindApplyRequest(order_id="B1", channel_extra=bind).validate()
        confirm_extra = {
            key: bind[key] for key in
            ("mch_user_id", "card_id", "card_name", "cert_id", "card_mp", "dc_type")
        }
        QuickPayBindConfirmRequest(
            order_id="B2", channel_extra=confirm_extra, verify_code="123456"
        ).validate()
        QuickPayConfirmRequest(mch_order_no="P1", verify_code="123456").validate()
        QuickPaySmsCheckRequest(pay_order_id="P2", verify_code="123456").validate()
        QuickPayUnbindRequest(
            order_id="B3",
            channel_extra={"mch_user_id": "U", "channel_card_id": "C"},
        ).validate()

    def test_electronic_account_eight_interfaces(self):
        user_info = {
            "name": "张三", "cert_no": "ID", "cert_validity_type": "0",
            "cert_begin_date": "20200101", "cert_end_date": "20300101",
            "mobile_no": "13800000000", "email": "a@example.com",
        }
        ElectronicAccountUserOpenRequest(
            order_id="U1", account_name="张三", account_type=1,
            relation_type="USER", account_alias="用户", division_profit="0.1",
            channel_extra={"user_info": user_info},
        ).validate()
        card = {
            "card_type": "0", "card_name": "公司", "card_no": "6222",
            "prov_id": "110000", "area_id": "110100", "mp": "13800000000",
            "bank_code": "ABC", "branch_name": "支行",
        }
        ElectronicAccountBusinessOpenRequest(
            order_id="BO1", account_no="A1",
            channel_extra={
                "card_info": card,
                "settle_config": {"settle_cycle": "T1", "settle_status": 1},
                "cash_config": [{"cash_type": "D0", "switch_state": 1, "out_fee_flag": 2}],
            },
        ).validate()
        ElectronicAccountUserQueryRequest(account_no="A1").validate()
        ElectronicAccountSettlementQueryRequest(
            order_id="S1", account_no="A1",
            begin_date="20260101", end_date="20260131",
        ).validate()
        ElectronicAccountBalanceWithdrawRequest(
            order_id="W1", account_no="A1", withdraw_amount="0.01"
        ).validate()
        ElectronicAccountSingleWithdrawQueryRequest(
            account_no="A1", withdraw_date="20260101", order_id="W1"
        ).validate()
        ElectronicAccountBatchWithdrawQueryRequest(
            account_no="A1", begin_date="20260101", end_date="20260109"
        ).validate()

    def test_withdraw_channel_status_protocol_values(self):
        for response_class in (
            ElectronicAccountSingleWithdrawQueryResponse,
            ElectronicAccountBatchWithdrawQueryResponse,
        ):
            for status in ("success", "succeess"):
                response = response_class({"status": 200, "data": {"status": status}})
                self.assertTrue(response.is_channel_successful())
        for status in ("success", "succeess"):
            response = ElectronicAccountBalanceWithdrawResponse({
                "status": 200, "data": {"status": status},
            })
            self.assertTrue(response.is_accepted())

    def test_official_response_shape_compatibility(self):
        flat_quick_pay = QuickPayBindApplyResponse({
            "trans_status": "S", "trans_id": "T1", "order_id": "O1",
        })
        self.assertTrue(flat_quick_pay.is_successful())
        self.assertEqual("T1", flat_quick_pay.get_trans_id())

        flat_balance = BalanceQueryResponse({
            "acct_type": "01", "acct_stat": "N", "balance_amt": "100",
        })
        self.assertFalse(flat_balance.is_error())
        self.assertEqual("100", flat_balance.get_balance_amount())

        direct = ElectronicAccountSettlementQueryResponse({
            "status": 200, "data": {"trans_id": "T1"},
        })
        listed = ElectronicAccountSettlementQueryResponse({
            "status": 200, "data": [{"trans_id": "T2"}],
        })
        self.assertEqual("T1", direct.get_trans_id())
        self.assertEqual("T2", listed.get_trans_id())

        batch = ElectronicAccountBatchWithdrawQueryResponse({
            "status": 200,
            "data": {"status": "succeess", "data": [{"order_id": "W1"}]},
        })
        self.assertEqual("W1", batch.get_order_id())

        task = DelayTransCreateTaskResponse({
            "status": 200, "data": {"status": "PENDING", "selected_orders": 2},
        })
        self.assertTrue(task.is_created())
        self.assertEqual(2, task.get_selected_orders())

    def test_dict_call_uses_typed_validation_and_response(self):
        body = '{"status":200,"msg":"ok","data":{"pay_order_id":"P1","order_state":0,"trans_status":"S"}}'
        with patch("urllib.request.urlopen", return_value=FakeResponse(body)):
            response = LsPayClient.execute({
                "funcCode": Endpoint.UNIFIED_ORDER,
                "params": {
                    "mch_order_no": "O1", "way_code": WayCode.ALI_QR,
                    "amount": 1, "subject": "商品", "body": "描述",
                    "channel_extra": {"pay_data_type": "code_img_url"},
                },
            })
        self.assertEqual("P1", response.get_pay_order_id())
        with self.assertRaisesRegex(LsPayValidationException, "^channel_extra 必填$"):
            LsPayClient.execute({
                "funcCode": Endpoint.UNIFIED_ORDER,
                "params": {
                    "mch_order_no": "O2", "way_code": WayCode.ALI_QR,
                    "amount": 1, "subject": "商品", "body": "描述",
                },
            })
        with self.assertRaises(LsPayValidationException):
            LsPayClient.execute({"funcCode": Endpoint.QUERY_ORDER, "params": {}})

    def test_log_sanitization(self):
        safe = LsPay.sanitize_for_log({
            "sign": "1234567890ABCDEF",
            "channel_extra": json.dumps({
                "card_no": "6222000012345678", "pay_data_type": "cashier"
            }),
        })
        encoded = json.dumps(safe, ensure_ascii=False)
        self.assertNotIn("6222000012345678", encoded)
        self.assertNotIn("1234567890ABCDEF", encoded)
        self.assertIn("pay_data_type", encoded)

    def test_payment_and_refund_notifications(self):
        payment = self._signed({
            "notify_type": "payment.succeeded", "event_type": "payment.succeeded",
            "pay_order_id": "P1", "mch_no": "TEST_MERCHANT", "app_id": "TEST_APP",
            "mch_order_no": "O1", "way_code": "WX_PUB", "amount": "100",
            "currency": "cny", "state": "2", "subject": "商品", "body": "描述",
            "add_time": "1790487185", "success_time": "1790487200",
            "req_time": "1790487185050",
        })
        notify = LsPayNotify.parse(payment)
        self.assertIsInstance(notify, PaymentNotify)
        self.assertTrue(notify.is_succeeded())
        notify.assert_order("O1", 100)
        tampered = dict(payment, amount="101")
        with self.assertRaises(LsPaySignException):
            LsPayNotify.parse(tampered)

        refund = self._signed({
            "notify_type": "refund", "event_type": "refund.succeeded",
            "pay_order_id": "P1", "mch_no": "TEST_MERCHANT", "app_id": "TEST_APP",
            "mch_refund_no": "R1", "way_code": "WX_PUB", "pay_amount": "100",
            "refund_amount": "40", "currency": "cny", "state": "2",
            "ext_param": "", "add_time": "2026-09-27 13:40:08",
            "success_time": "2026-09-27 13:40:09", "req_time": "1790487608",
        })
        parsed = LsPayNotify.parse(refund)
        self.assertIsInstance(parsed, RefundNotify)
        self.assertTrue(parsed.is_succeeded())

    def test_delay_transfer_notification(self):
        data = self._signed({
            "task_id": "T1", "mch_no": "TEST_MERCHANT", "app_id": "TEST_APP",
            "order_no": "O1", "target_amount": "100", "processed_amount": "100",
            "remaining_amount": "0", "status": "COMPLETED", "progress": "100",
            "account_no": "A1", "account_name": "张三",
        })
        notify = LsPayNotify.parse(data)
        self.assertIsInstance(notify, DelayTransTransferNotify)
        self.assertTrue(notify.is_succeeded())

    @staticmethod
    def _signed(values):
        values = dict(values)
        values["sign"] = LsPayTools.sign(values, "test-secret")
        return values


if __name__ == "__main__":
    unittest.main()
