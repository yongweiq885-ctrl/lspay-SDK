package lspay

import (
	"context"
	"strings"
	"testing"
)

func TestCashierUsesBrowserForm(t *testing.T) {
	srv, got := newTestServer(t, 200, `{"status":200}`)
	client := newTestClient(t, srv.URL)
	req := &UnifiedOrderRequest{
		MchOrderNo: "C1", WayCode: WayCodeAliQR, Amount: 1, Subject: "商品", Body: "描述",
		ChannelExtra: &UnifiedOrderChannelExtra{PayDataType: PayDataTypeCashier},
	}

	if _, err := client.UnifiedOrder(context.Background(), req); err == nil || !strings.Contains(err.Error(), "BuildCashierForm") {
		t.Fatalf("cashier 不应由服务端调用接口: %v", err)
	}
	form, err := client.BuildCashierForm(req)
	if err != nil {
		t.Fatal(err)
	}
	if got.path != "" {
		t.Fatalf("不应发出 HTTP 请求: %s", got.path)
	}
	if form.Method != "POST" || !strings.HasSuffix(form.Action, EndpointUnifiedOrder) {
		t.Fatalf("表单地址不符: %+v", form)
	}
	if !VerifySign(form.Fields, testConfig.MD5Key) {
		t.Fatal("表单签名不正确")
	}
	if form.Fields["channel_extra"] != `{"pay_data_type":"cashier"}` {
		t.Fatalf("channel_extra 不符: %s", form.Fields["channel_extra"])
	}
	if h := form.HTML(); !strings.Contains(h, `name="sign"`) || !strings.Contains(h, ".submit()") {
		t.Fatalf("HTML 不符: %s", h)
	}

	req.ChannelExtra = &UnifiedOrderChannelExtra{PayDataType: PayDataTypePayURL}
	if _, err := client.BuildCashierForm(req); err == nil {
		t.Fatal("非 cashier 不应生成收银台表单")
	}
}
