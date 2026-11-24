package lspay

import (
	"encoding/json"
	"os"
	"strings"
)

// MerchantConfig 是商户凭证，三项都在立收通商户控台的应用信息里查看。
type MerchantConfig struct {
	MchNo  string `json:"mch_no"`
	AppID  string `json:"app_id"`
	MD5Key string `json:"md5_key"`
	// NotifyURL 和 ReturnURL 是统一下单的默认回调地址，请求里没填时使用。
	NotifyURL string `json:"notify_url"`
	ReturnURL string `json:"return_url"`
}

// Validate 检查凭证是否齐全。
func (c MerchantConfig) Validate() error {
	if strings.TrimSpace(c.MchNo) == "" || strings.TrimSpace(c.AppID) == "" || c.MD5Key == "" {
		return configError("mch_no、app_id、md5_key 均必填")
	}
	if textLen(c.MchNo) > 30 {
		return configError("mch_no 长度不能超过 30")
	}
	if textLen(c.AppID) > 24 {
		return configError("app_id 长度不能超过 24")
	}
	for field, value := range map[string]string{"notify_url": c.NotifyURL, "return_url": c.ReturnURL} {
		if err := checkHTTPURL(field, value, 128); err != nil {
			return configError("%s 必须是长度不超过 128 的 HTTP(S) URL", field)
		}
	}
	return nil
}

// LoadConfig 读取 JSON 配置文件，再用环境变量覆盖：
// LSPAY_MCH_NO、LSPAY_APP_ID、LSPAY_MD5_KEY、LSPAY_NOTIFY_URL、LSPAY_RETURN_URL。
// path 为空时只读环境变量。
func LoadConfig(path string) (MerchantConfig, error) {
	var cfg MerchantConfig
	if path != "" {
		raw, err := os.ReadFile(path)
		if err != nil {
			return cfg, &Error{Kind: ErrKindConfig, Message: "读取配置文件失败: " + path, Err: err}
		}
		if err := json.Unmarshal(raw, &cfg); err != nil {
			return cfg, &Error{Kind: ErrKindConfig, Message: "配置文件不是合法 JSON: " + path, Err: err}
		}
	}
	overrides := []struct {
		env    string
		target *string
	}{
		{"LSPAY_MCH_NO", &cfg.MchNo},
		{"LSPAY_APP_ID", &cfg.AppID},
		{"LSPAY_MD5_KEY", &cfg.MD5Key},
		{"LSPAY_NOTIFY_URL", &cfg.NotifyURL},
		{"LSPAY_RETURN_URL", &cfg.ReturnURL},
	}
	for _, o := range overrides {
		if v := os.Getenv(o.env); v != "" {
			*o.target = v
		}
	}
	cfg.MchNo = strings.TrimSpace(cfg.MchNo)
	cfg.AppID = strings.TrimSpace(cfg.AppID)
	cfg.NotifyURL = strings.TrimSpace(cfg.NotifyURL)
	cfg.ReturnURL = strings.TrimSpace(cfg.ReturnURL)
	return cfg, cfg.Validate()
}
