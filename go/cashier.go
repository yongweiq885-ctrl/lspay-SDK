package lspay

import (
	"encoding/json"
	"html"
	"sort"
	"strings"
)

// CashierForm 是 pay_data_type=cashier 的收银台表单：由用户浏览器把 Fields 以 POST 提交到 Action。
type CashierForm struct {
	Action string
	Method string
	Fields map[string]string
}

// BuildCashierForm 生成收银台表单。cashier 时网关返回收银台页面，不能用 UnifiedOrder 在服务端调用。
func (c *Client) BuildCashierForm(req *UnifiedOrderRequest) (*CashierForm, error) {
	if req == nil {
		return nil, validationError("request 不能为空")
	}
	biz, err := req.Params()
	if err != nil {
		return nil, err
	}
	if !isCashierOrder(biz["channel_extra"]) {
		return nil, validationError("BuildCashierForm 仅用于 channel_extra.pay_data_type=cashier")
	}
	fields, err := c.signForm(req.Endpoint(), biz)
	if err != nil {
		return nil, err
	}
	return &CashierForm{Action: c.endpointURL(req.Endpoint()), Method: "POST", Fields: fields}, nil
}

// HTML 返回自动提交到立收通收银台的 HTML 表单。
func (f *CashierForm) HTML() string {
	names := make([]string, 0, len(f.Fields))
	for name := range f.Fields {
		names = append(names, name)
	}
	sort.Strings(names)
	var b strings.Builder
	b.WriteString(`<form id="lspayCashierForm" method="POST" action="` + html.EscapeString(f.Action) + `">`)
	for _, name := range names {
		b.WriteString(`<input type="hidden" name="` + html.EscapeString(name) +
			`" value="` + html.EscapeString(f.Fields[name]) + `">`)
	}
	b.WriteString(`</form><script>document.getElementById("lspayCashierForm").submit();</script>`)
	return b.String()
}

func isCashierOrder(channelExtra string) bool {
	if strings.TrimSpace(channelExtra) == "" {
		return false
	}
	var extra map[string]interface{}
	if err := json.Unmarshal([]byte(channelExtra), &extra); err != nil {
		return false
	}
	v, _ := extra["pay_data_type"].(string)
	return v == PayDataTypeCashier
}
