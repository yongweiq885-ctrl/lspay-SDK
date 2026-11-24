package lspay

import (
	"context"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"
)

const maxResponseBytes = 4 << 20

// Client 绑定一个商户。多商户时每个商户创建一个 Client，回调地址也按商户分开。
// Client 可并发使用。资金类请求不会自动重试：超时后先用原单号查询，再决定是否用同一单号重试。
type Client struct {
	config     MerchantConfig
	baseURL    string
	httpClient *http.Client
	userAgent  string
	logger     func(line string)
	now        func() time.Time
}

// Option 配置 Client。
type Option func(*Client)

// WithHTTPClient 使用自定义 http.Client（代理、连接池等）。
func WithHTTPClient(hc *http.Client) Option { return func(c *Client) { c.httpClient = hc } }

// WithBaseURL 修改网关地址，默认 DefaultBaseURL。
func WithBaseURL(base string) Option { return func(c *Client) { c.baseURL = base } }

// WithTimeout 设置整个请求的超时，默认 30 秒。用 WithHTTPClient 时以传入的 http.Client 为准。
func WithTimeout(d time.Duration) Option {
	return func(c *Client) { c.httpClient = &http.Client{Timeout: d} }
}

// WithUserAgent 设置 User-Agent。
func WithUserAgent(ua string) Option { return func(c *Client) { c.userAgent = ua } }

// WithLogger 接收请求和响应日志。日志里的敏感字段已打码。
func WithLogger(fn func(line string)) Option { return func(c *Client) { c.logger = fn } }

// NewClient 创建 Client。凭证不完整时返回配置错误。
func NewClient(cfg MerchantConfig, opts ...Option) (*Client, error) {
	if err := cfg.Validate(); err != nil {
		return nil, err
	}
	c := &Client{
		config:     cfg,
		baseURL:    DefaultBaseURL,
		httpClient: &http.Client{Timeout: 30 * time.Second},
		userAgent:  "LsPaySdk-go/" + Version,
		now:        time.Now,
	}
	for _, opt := range opts {
		opt(c)
	}
	u, err := url.Parse(c.baseURL)
	if err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Host == "" {
		return nil, configError("网关地址非法: %s", c.baseURL)
	}
	if !strings.HasSuffix(c.baseURL, "/") {
		c.baseURL += "/"
	}
	return c, nil
}

// MchNo 返回当前商户号。
func (c *Client) MchNo() string { return c.config.MchNo }

// Do 校验请求、签名并发送，返回未类型化的响应。
func (c *Client) Do(ctx context.Context, req Request) (*Response, error) {
	if req == nil {
		return nil, validationError("request 不能为空")
	}
	biz, err := req.Params()
	if err != nil {
		return nil, err
	}
	return c.Post(ctx, req.Endpoint(), biz)
}

// Post 直接调用接口，biz 为业务参数。用于 SDK 尚未封装的新接口；已封装的接口请用对应方法。
func (c *Client) Post(ctx context.Context, path string, biz map[string]string) (*Response, error) {
	if strings.Trim(path, "/") == EndpointUnifiedOrder && isCashierOrder(biz["channel_extra"]) {
		return nil, validationError("pay_data_type=cashier 时网关返回收银台页面，不能由服务端调用接口；" +
			"请使用 Client.BuildCashierForm 生成表单，由用户浏览器提交")
	}
	form, err := c.signForm(path, biz)
	if err != nil {
		return nil, err
	}

	values := url.Values{}
	for k, v := range form {
		values.Set(k, v)
	}
	endpoint := c.endpointURL(path)
	c.log("REQUEST POST", endpoint, SanitizeForLog(form))

	if ctx == nil {
		ctx = context.Background()
	}
	httpReq, err := http.NewRequestWithContext(ctx, http.MethodPost, endpoint, strings.NewReader(values.Encode()))
	if err != nil {
		return nil, &Error{Kind: ErrKindValidation, Message: "构造 HTTP 请求失败", Err: err}
	}
	httpReq.Header.Set("Content-Type", "application/x-www-form-urlencoded;charset=utf-8")
	httpReq.Header.Set("Accept", "application/json")
	httpReq.Header.Set("User-Agent", c.userAgent)

	resp, err := c.httpClient.Do(httpReq)
	if err != nil {
		return nil, &Error{Kind: ErrKindNetwork, Message: "请求立收通失败", Err: err}
	}
	defer resp.Body.Close()
	raw, err := io.ReadAll(io.LimitReader(resp.Body, maxResponseBytes))
	if err != nil {
		return nil, &Error{Kind: ErrKindNetwork, Message: "读取立收通响应失败", HTTPStatus: resp.StatusCode, Err: err}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return nil, &Error{Kind: ErrKindNetwork, HTTPStatus: resp.StatusCode,
			Message: fmt.Sprintf("HTTP %d", resp.StatusCode)}
	}
	parsed, err := ParseResponse(raw)
	if err != nil {
		return nil, err
	}
	c.log("RESPONSE", endpoint, sanitizeResponse(parsed.body))
	return parsed, nil
}

func (c *Client) endpointURL(path string) string {
	return c.baseURL + strings.TrimPrefix(path, "/")
}

// signForm 注入公共字段并签名，返回可直接提交的参数。
func (c *Client) signForm(path string, biz map[string]string) (map[string]string, error) {
	for _, key := range reservedParams {
		if _, ok := biz[key]; ok {
			return nil, validationError("%s 是 SDK 管理的公共参数，业务参数不允许覆盖", key)
		}
	}
	form := make(map[string]string, len(biz)+6)
	for k, v := range biz {
		if v != "" {
			form[k] = v
		}
	}
	if strings.Trim(path, "/") == EndpointUnifiedOrder {
		if form["notify_url"] == "" && c.config.NotifyURL != "" {
			form["notify_url"] = c.config.NotifyURL
		}
		if form["return_url"] == "" && c.config.ReturnURL != "" {
			form["return_url"] = c.config.ReturnURL
		}
	}
	form["mch_no"] = c.config.MchNo
	form["app_id"] = c.config.AppID
	form["req_time"] = strconv.FormatInt(c.now().UnixNano()/int64(time.Millisecond), 10)
	form["version"] = apiVersion
	form["sign_type"] = signType
	form["sign"] = Sign(form, c.config.MD5Key)
	return form, nil
}

func (c *Client) log(prefix, endpoint string, payload interface{}) {
	if c.logger == nil {
		return
	}
	b, err := marshalJSON(payload)
	if err != nil {
		b = []byte("[unserializable]")
	}
	c.logger(prefix + " " + endpoint + " " + string(b))
}

func (c *Client) UnifiedOrder(ctx context.Context, req *UnifiedOrderRequest) (*UnifiedOrderResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &UnifiedOrderResponse{*r}, nil
}

func (c *Client) QuickPayApply(ctx context.Context, req *QuickPayApplyRequest) (*QuickPayApplyResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &QuickPayApplyResponse{UnifiedOrderResponse{*r}}, nil
}

func (c *Client) BalancePay(ctx context.Context, req *BalancePayRequest) (*BalancePayResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &BalancePayResponse{UnifiedOrderResponse{*r}}, nil
}

func (c *Client) QueryOrder(ctx context.Context, req *QueryOrderRequest) (*QueryOrderResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &QueryOrderResponse{*r}, nil
}

func (c *Client) Refund(ctx context.Context, req *RefundRequest) (*RefundResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &RefundResponse{*r}, nil
}

func (c *Client) QueryRefund(ctx context.Context, req *QueryRefundRequest) (*QueryRefundResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &QueryRefundResponse{RefundResponse{*r}}, nil
}

func (c *Client) DelayTransConfirm(ctx context.Context, req *DelayTransConfirmRequest) (*DelayTransConfirmResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &DelayTransConfirmResponse{*r}, nil
}

func (c *Client) DelayTransConfirmRefund(ctx context.Context, req *DelayTransConfirmRefundRequest) (*DelayTransConfirmRefundResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &DelayTransConfirmRefundResponse{DelayTransConfirmResponse{*r}}, nil
}

func (c *Client) DelayTransUnconfirmQuery(ctx context.Context, req *DelayTransUnconfirmQueryRequest) (*DelayTransUnconfirmQueryResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &DelayTransUnconfirmQueryResponse{*r}, nil
}

func (c *Client) DelayTransCreateTask(ctx context.Context, req *DelayTransCreateTaskRequest) (*DelayTransCreateTaskResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &DelayTransCreateTaskResponse{*r}, nil
}

func (c *Client) QuickPayBindApply(ctx context.Context, req *QuickPayBindApplyRequest) (*QuickPayBindApplyResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &QuickPayBindApplyResponse{transResponse{*r}}, nil
}

func (c *Client) QuickPayBindConfirm(ctx context.Context, req *QuickPayBindConfirmRequest) (*QuickPayBindConfirmResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &QuickPayBindConfirmResponse{transResponse{*r}}, nil
}

func (c *Client) QuickPayConfirm(ctx context.Context, req *QuickPayConfirmRequest) (*QuickPayConfirmResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &QuickPayConfirmResponse{transResponse{*r}}, nil
}

func (c *Client) QuickPaySmsCheck(ctx context.Context, req *QuickPaySmsCheckRequest) (*QuickPaySmsCheckResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &QuickPaySmsCheckResponse{transResponse{*r}}, nil
}

func (c *Client) QuickPayUnbind(ctx context.Context, req *QuickPayUnbindRequest) (*QuickPayUnbindResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &QuickPayUnbindResponse{transResponse{*r}}, nil
}

func (c *Client) ElectronicAccountUserOpen(ctx context.Context, req *ElectronicAccountUserOpenRequest) (*ElectronicAccountUserOpenResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &ElectronicAccountUserOpenResponse{accountResponse{transResponse{*r}}}, nil
}

func (c *Client) ElectronicAccountBusinessOpen(ctx context.Context, req *ElectronicAccountBusinessOpenRequest) (*ElectronicAccountBusinessOpenResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &ElectronicAccountBusinessOpenResponse{accountResponse{transResponse{*r}}}, nil
}

func (c *Client) ElectronicAccountBusinessChange(ctx context.Context, req *ElectronicAccountBusinessChangeRequest) (*ElectronicAccountBusinessChangeResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &ElectronicAccountBusinessChangeResponse{accountResponse{transResponse{*r}}}, nil
}

func (c *Client) ElectronicAccountUserQuery(ctx context.Context, req *ElectronicAccountUserQueryRequest) (*ElectronicAccountUserQueryResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &ElectronicAccountUserQueryResponse{*r}, nil
}

func (c *Client) ElectronicAccountSettlementQuery(ctx context.Context, req *ElectronicAccountSettlementQueryRequest) (*ElectronicAccountSettlementQueryResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &ElectronicAccountSettlementQueryResponse{*r}, nil
}

func (c *Client) ElectronicAccountBalanceWithdraw(ctx context.Context, req *ElectronicAccountBalanceWithdrawRequest) (*ElectronicAccountBalanceWithdrawResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &ElectronicAccountBalanceWithdrawResponse{*r}, nil
}

func (c *Client) ElectronicAccountSingleWithdrawQuery(ctx context.Context, req *ElectronicAccountSingleWithdrawQueryRequest) (*ElectronicAccountSingleWithdrawQueryResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &ElectronicAccountSingleWithdrawQueryResponse{*r}, nil
}

func (c *Client) ElectronicAccountBatchWithdrawQuery(ctx context.Context, req *ElectronicAccountBatchWithdrawQueryRequest) (*ElectronicAccountBatchWithdrawQueryResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &ElectronicAccountBatchWithdrawQueryResponse{*r}, nil
}

func (c *Client) BalanceQuery(ctx context.Context, req *BalanceQueryRequest) (*BalanceQueryResponse, error) {
	r, err := c.Do(ctx, req)
	if err != nil {
		return nil, err
	}
	return &BalanceQueryResponse{*r}, nil
}
