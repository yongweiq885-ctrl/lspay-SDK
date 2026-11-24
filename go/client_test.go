package lspay

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

var testConfig = MerchantConfig{
	MchNo: "TEST_MERCHANT", AppID: "TEST_APP", MD5Key: "test-secret",
	NotifyURL: "https://merchant.test/notify",
}

type captured struct {
	path        string
	contentType string
	form        map[string]string
}

func newTestServer(t *testing.T, status int, body string) (*httptest.Server, *captured) {
	t.Helper()
	c := &captured{}
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		c.path = r.URL.Path
		c.contentType = r.Header.Get("Content-Type")
		if err := r.ParseForm(); err != nil {
			t.Error(err)
		}
		c.form = map[string]string{}
		for k, v := range r.PostForm {
			c.form[k] = v[0]
		}
		w.WriteHeader(status)
		_, _ = w.Write([]byte(body))
	}))
	t.Cleanup(srv.Close)
	return srv, c
}

func newTestClient(t *testing.T, baseURL string, opts ...Option) *Client {
	t.Helper()
	c, err := NewClient(testConfig, append([]Option{WithBaseURL(baseURL)}, opts...)...)
	if err != nil {
		t.Fatal(err)
	}
	return c
}

func TestClientSendsSignedFormAndParsesNumericStatus(t *testing.T) {
	srv, got := newTestServer(t, 200, `{"status":200,"msg":"ok","data":{"pay_order_id":"P1","order_state":1,"trans_status":"S","pay_info":"https://pay"}}`)
	var logs []string
	client := newTestClient(t, srv.URL, WithLogger(func(line string) { logs = append(logs, line) }))

	resp, err := client.UnifiedOrder(context.Background(), &UnifiedOrderRequest{
		MchOrderNo: "O1", WayCode: WayCodeAliQR, Amount: 1, Subject: "商品", Body: "描述",
		ChannelExtra: &UnifiedOrderChannelExtra{PayDataType: PayDataTypeCodeImgURL},
	})
	if err != nil {
		t.Fatal(err)
	}
	if got.path != "/gateway/unifiedorder" || !strings.HasPrefix(got.contentType, "application/x-www-form-urlencoded") {
		t.Fatalf("请求路径或类型不符: %s %s", got.path, got.contentType)
	}
	for k, want := range map[string]string{
		"mch_no": "TEST_MERCHANT", "app_id": "TEST_APP", "version": "1.0", "sign_type": "MD5",
		"notify_url": "https://merchant.test/notify", "channel_extra": `{"pay_data_type":"code_img_url"}`,
	} {
		if got.form[k] != want {
			t.Errorf("%s = %q, want %q", k, got.form[k], want)
		}
	}
	if len(got.form["req_time"]) != 13 {
		t.Errorf("req_time 应为 13 位毫秒: %s", got.form["req_time"])
	}
	if _, ok := got.form["md5_key"]; ok {
		t.Error("md5_key 不能进入请求体")
	}
	if !VerifySign(got.form, "test-secret") {
		t.Error("请求签名无效")
	}
	if resp.IsError() || !resp.IsAccepted() || resp.PayOrderID() != "P1" || resp.PayInfo() != "https://pay" {
		t.Fatalf("响应解析不符: %s", resp.Raw)
	}
	if state, ok := resp.OrderState(); !ok || state != OrderStatePaying {
		t.Fatalf("order_state = %d, %v", state, ok)
	}
	if len(logs) != 2 || strings.Contains(strings.Join(logs, ""), got.form["sign"]) {
		t.Fatalf("日志不符或未脱敏: %v", logs)
	}
}

func TestNotifyURLOnlyInjectedForUnifiedOrder(t *testing.T) {
	srv, got := newTestServer(t, 200, `{"status":200,"data":"{\"state\":2,\"pay_order_id\":\"P1\"}"}`)
	client := newTestClient(t, srv.URL)
	resp, err := client.QueryOrder(context.Background(), &QueryOrderRequest{MchOrderNo: "O1"})
	if err != nil {
		t.Fatal(err)
	}
	if _, ok := got.form["notify_url"]; ok {
		t.Fatal("订单查询不应带 notify_url")
	}
	if !resp.IsPaid() || resp.PayOrderID() != "P1" {
		t.Fatalf("字符串 data 解析失败: %s", resp.Raw)
	}
}

func TestClientRejectsReservedParams(t *testing.T) {
	client := newTestClient(t, "https://api.lspay.test/")
	_, err := client.QueryOrder(context.Background(), &QueryOrderRequest{
		MchOrderNo: "O1", ExtendInfo: map[string]string{"mch_no": "OTHER"},
	})
	if !IsKind(err, ErrKindValidation) || !strings.Contains(err.Error(), "mch_no") {
		t.Fatalf("应拒绝覆盖公共参数: %v", err)
	}
}

func TestClientErrors(t *testing.T) {
	srv, _ := newTestServer(t, 502, "bad gateway")
	_, err := newTestClient(t, srv.URL).QueryOrder(context.Background(), &QueryOrderRequest{MchOrderNo: "O1"})
	var e *Error
	if !errors.As(err, &e) || e.Kind != ErrKindNetwork || e.HTTPStatus != 502 {
		t.Fatalf("期望 HTTP 502 网络错误: %v", err)
	}

	srv, _ = newTestServer(t, 200, "<html>")
	_, err = newTestClient(t, srv.URL).QueryOrder(context.Background(), &QueryOrderRequest{MchOrderNo: "O1"})
	if !IsKind(err, ErrKindProtocol) {
		t.Fatalf("期望协议错误: %v", err)
	}

	slow := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		time.Sleep(200 * time.Millisecond)
	}))
	defer slow.Close()
	ctx, cancel := context.WithTimeout(context.Background(), 20*time.Millisecond)
	defer cancel()
	_, err = newTestClient(t, slow.URL).QueryOrder(ctx, &QueryOrderRequest{MchOrderNo: "O1"})
	if !IsKind(err, ErrKindNetwork) {
		t.Fatalf("期望超时网络错误: %v", err)
	}

	srv, _ = newTestServer(t, 200, `{"status":400,"msg":"订单不存在"}`)
	resp, err := newTestClient(t, srv.URL).QueryOrder(context.Background(), &QueryOrderRequest{MchOrderNo: "O1"})
	if err != nil || !resp.IsError() || !IsKind(resp.Err(), ErrKindBiz) || resp.ErrorInfo() != "[400] 订单不存在" {
		t.Fatalf("业务错误处理不符: %v %v", resp, err)
	}
}

func TestNewClientRejectsIncompleteConfig(t *testing.T) {
	if _, err := NewClient(MerchantConfig{MchNo: "M", AppID: "A"}); !IsKind(err, ErrKindConfig) {
		t.Fatalf("缺少 md5_key 应返回配置错误: %v", err)
	}
	cfg := testConfig
	cfg.NotifyURL = "not-a-url"
	if _, err := NewClient(cfg); !IsKind(err, ErrKindConfig) {
		t.Fatalf("非法 notify_url 应返回配置错误: %v", err)
	}
}

func TestLoadConfigEnvironmentOverride(t *testing.T) {
	t.Setenv("LSPAY_MCH_NO", "ENV_MCH")
	t.Setenv("LSPAY_APP_ID", "ENV_APP")
	t.Setenv("LSPAY_MD5_KEY", "env-key")
	cfg, err := LoadConfig("")
	if err != nil || cfg.MchNo != "ENV_MCH" || cfg.MD5Key != "env-key" {
		t.Fatalf("环境变量配置不符: %+v %v", cfg, err)
	}
}

func TestResponseShapes(t *testing.T) {
	parse := func(body string) Response {
		r, err := ParseResponse([]byte(body))
		if err != nil {
			t.Fatal(err)
		}
		return *r
	}

	flatBind := QuickPayBindApplyResponse{transResponse{parse(`{"trans_status":"S","trans_id":"T1","order_id":"O1"}`)}}
	if flatBind.IsError() || !flatBind.IsSuccessful() || flatBind.TransID() != "T1" || flatBind.Err() != nil {
		t.Fatal("扁平快捷支付响应解析失败")
	}
	flatFail := QuickPayBindApplyResponse{transResponse{parse(`{"trans_status":"F","resp_desc":"卡号错误"}`)}}
	if !flatFail.IsError() || flatFail.ErrorInfo() != "[F] 卡号错误" {
		t.Fatalf("扁平失败响应: %s", flatFail.ErrorInfo())
	}

	bound := QuickPayBindConfirmResponse{transResponse{parse(`{"status":200,"data":{"trans_status":"S","channel_user":"U","channel_card_id":"C"}}`)}}
	if !bound.HasBinding() {
		t.Fatal("绑卡确认应有绑定结果")
	}

	balance := BalanceQueryResponse{parse(`{"acct_type":"01","acct_stat":"N","balance_amt":"100.00"}`)}
	if balance.IsError() || !balance.IsNormal() || balance.BalanceAmount() != "100.00" {
		t.Fatal("扁平余额响应解析失败")
	}

	direct := ElectronicAccountSettlementQueryResponse{parse(`{"status":200,"data":{"trans_id":"T1"}}`)}
	listed := ElectronicAccountSettlementQueryResponse{parse(`{"status":200,"data":[{"trans_id":"T2"}]}`)}
	if direct.TransID() != "T1" || listed.TransID() != "T2" {
		t.Fatal("结算记录解析失败")
	}

	batch := ElectronicAccountBatchWithdrawQueryResponse{parse(`{"status":200,"data":{"status":"succeess","count":1,"data":[{"order_id":"W1"}]}}`)}
	if !batch.IsChannelSuccessful() || batch.Count() != 1 || batch.Records()[0]["order_id"] != "W1" {
		t.Fatal("批量提现响应解析失败")
	}
	for _, status := range []string{"success", "succeess"} {
		w := ElectronicAccountBalanceWithdrawResponse{parse(`{"status":200,"data":{"status":"` + status + `"}}`)}
		if !w.IsAccepted() {
			t.Fatalf("%s 应表示已受理", status)
		}
	}

	task := DelayTransCreateTaskResponse{parse(`{"status":200,"data":{"status":"PENDING","selected_orders":2}}`)}
	if !task.IsCreated() || task.SelectedOrders() != 2 {
		t.Fatal("转账任务响应解析失败")
	}

	userQuery := ElectronicAccountUserQueryResponse{parse(`{"status":200,"data":{"trans_status":"S","channel_status":1,"channel_extra":"{\"settle_cycle\":\"D1\"}"}}`)}
	if !userQuery.IsOnboarded() || userQuery.ChannelExtra()["settle_cycle"] != "D1" {
		t.Fatal("用户查询响应解析失败")
	}

	wxLite := UnifiedOrderResponse{parse(`{"status":200,"data":{"pay_info":"{\"timeStamp\":\"1\",\"signType\":\"RSA\"}"}}`)}
	if wxLite.PayInfoMap()["signType"] != "RSA" {
		t.Fatal("pay_info JSON 字符串解析失败")
	}
}
