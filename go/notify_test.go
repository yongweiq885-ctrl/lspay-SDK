package lspay

import (
	"bytes"
	"mime/multipart"
	"net/http/httptest"
	"net/url"
	"strings"
	"testing"
)

func signed(values map[string]string) map[string]string {
	out := map[string]string{}
	for k, v := range values {
		out[k] = v
	}
	out["sign"] = Sign(out, testConfig.MD5Key)
	return out
}

func paymentForm() map[string]string {
	return signed(map[string]string{
		"notify_type": "payment.succeeded", "event_type": "payment.succeeded",
		"pay_order_id": "P1", "mch_no": "TEST_MERCHANT", "app_id": "TEST_APP",
		"mch_order_no": "O1", "way_code": "WX_PUB", "amount": "100", "currency": "cny",
		"state": "2", "subject": "商品", "body": "描述", "ext_param": "",
		"add_time": "1790487185", "success_time": "1790487200", "req_time": "1790487185050",
	})
}

func TestPaymentNotify(t *testing.T) {
	n, err := ParseNotify(paymentForm(), testConfig)
	if err != nil {
		t.Fatal(err)
	}
	payment, ok := n.(*PaymentNotify)
	if !ok || !payment.IsSucceeded() || payment.Amount() != 100 {
		t.Fatalf("支付通知解析不符: %#v", n)
	}
	if err := payment.AssertOrder("O1", 100, "CNY"); err != nil {
		t.Fatal(err)
	}
	if err := payment.AssertOrder("O1", 101, ""); err == nil {
		t.Fatal("金额不一致应报错")
	}
	if payment.IdempotencyKey() != "payment:P1:payment.succeeded" {
		t.Fatalf("幂等键: %s", payment.IdempotencyKey())
	}

	tampered := paymentForm()
	tampered["amount"] = "1"
	if _, err := ParseNotify(tampered, testConfig); !IsKind(err, ErrKindSign) {
		t.Fatalf("篡改后应验签失败: %v", err)
	}

	unsigned := paymentForm()
	delete(unsigned, "sign")
	if _, err := ParseNotify(unsigned, testConfig); !IsKind(err, ErrKindSign) {
		t.Fatalf("缺少 sign 应拒绝: %v", err)
	}

	other := testConfig
	other.MchNo = "OTHER"
	if _, err := ParseNotify(paymentForm(), other); err == nil {
		t.Fatal("商户号不一致应拒绝")
	}

	millis := paymentForm()
	millis["add_time"] = "1790487185000"
	millis = signed(millis)
	if n, err := ParseNotify(millis, testConfig); err == nil || n != nil {
		t.Fatalf("13 位 add_time 应拒绝且不返回通知: %v %v", n, err)
	}

	withNewField := paymentForm()
	withNewField["future_field"] = "x"
	withNewField = signed(withNewField)
	if _, err := ParseNotify(withNewField, testConfig); err != nil {
		t.Fatalf("新增字段参与验签后应通过: %v", err)
	}
}

func TestRefundNotify(t *testing.T) {
	form := signed(map[string]string{
		"notify_type": "refund", "event_type": "refund.succeeded",
		"pay_order_id": "P1", "mch_no": "TEST_MERCHANT", "app_id": "TEST_APP",
		"mch_refund_no": "R1", "way_code": "WX_PUB", "pay_amount": "100",
		"refund_amount": "40", "currency": "cny", "state": "2", "ext_param": "",
		"add_time": "2026-09-27 13:40:08", "success_time": "2026-09-27 13:40:09",
		"req_time": "1790487608",
	})
	n, err := ParseNotify(form, testConfig)
	if err != nil {
		t.Fatal(err)
	}
	refund, ok := n.(*RefundNotify)
	if !ok || !refund.IsSucceeded() {
		t.Fatalf("退款通知解析不符: %#v", n)
	}
	if err := refund.AssertRefund("R1", 40, "cny", 100); err != nil {
		t.Fatal(err)
	}
	if refund.IdempotencyKey() != "refund:R1:refund.succeeded" {
		t.Fatalf("幂等键: %s", refund.IdempotencyKey())
	}

	over := map[string]string{}
	for k, v := range form {
		over[k] = v
	}
	over["refund_amount"] = "200"
	if _, err := ParseNotify(signed(over), testConfig); err == nil {
		t.Fatal("退款金额大于支付金额应拒绝")
	}
}

func TestDelayTransTransferNotify(t *testing.T) {
	form := signed(map[string]string{
		"task_id": "T1", "mch_no": "TEST_MERCHANT", "app_id": "TEST_APP",
		"order_no": "O1", "target_amount": "100", "processed_amount": "100",
		"remaining_amount": "0", "status": "COMPLETED", "progress": "100.0",
		"account_no": "A1", "account_name": "张三", "completed_time": "", "remark": "",
	})
	n, err := ParseNotify(form, testConfig)
	if err != nil {
		t.Fatal(err)
	}
	transfer, ok := n.(*DelayTransTransferNotify)
	if !ok || !transfer.IsSucceeded() || transfer.AssertTask("O1", 100) != nil {
		t.Fatalf("转账通知解析不符: %#v", n)
	}

	partial := map[string]string{}
	for k, v := range form {
		partial[k] = v
	}
	partial["status"] = "PROCESSING"
	partial["remaining_amount"] = "10"
	if _, err := ParseNotify(signed(partial), testConfig); err == nil {
		t.Fatal("金额关系不一致应拒绝")
	}
}

func TestReadNotifyForm(t *testing.T) {
	form := paymentForm()

	var body bytes.Buffer
	mw := multipart.NewWriter(&body)
	for k, v := range form {
		_ = mw.WriteField(k, v)
	}
	_ = mw.Close()
	req := httptest.NewRequest("POST", "/lspay/notify", &body)
	req.Header.Set("Content-Type", mw.FormDataContentType())
	got, err := ReadNotifyForm(req)
	if err != nil || len(got) != len(form) || got["sign"] != form["sign"] {
		t.Fatalf("multipart 读取不符: %v %v", got, err)
	}

	values := url.Values{}
	for k, v := range form {
		values.Set(k, v)
	}
	req = httptest.NewRequest("POST", "/lspay/notify", strings.NewReader(values.Encode()))
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	got, err = ReadNotifyForm(req)
	if err != nil || got["mch_order_no"] != "O1" {
		t.Fatalf("urlencoded 读取不符: %v %v", got, err)
	}

	values.Add("amount", "1")
	req = httptest.NewRequest("POST", "/lspay/notify", strings.NewReader(values.Encode()))
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	if _, err := ReadNotifyForm(req); err == nil {
		t.Fatal("重复字段应拒绝")
	}
}
