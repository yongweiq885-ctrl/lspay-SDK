package lspay

import "unicode/utf8"

var sensitiveLogKeys = map[string]bool{
	"sign": true, "md5_key": true, "auth_code": true, "openid": true, "buyer_id": true,
	"trans_in": true, "trans_out": true, "account_no": true, "account_name": true,
	"account_alias": true, "mch_user_id": true, "card_id": true, "card_name": true,
	"card_no": true, "cert_id": true, "cert_no": true, "cert_begin_date": true,
	"cert_end_date": true, "card_mp": true, "vip_code": true, "expiration": true,
	"verify_code": true, "channel_user": true, "channel_card_id": true, "name": true,
	"mobile_no": true, "email": true, "reg_name": true, "short_name": true,
	"license_code": true, "reg_detail": true, "legal_name": true, "legal_cert_no": true,
	"contact_name": true, "contact_mobile": true, "mp": true, "pay_info": true,
}

// SanitizeForLog 返回可写入日志的参数副本：签名、密钥、账户、银行卡、证件、手机号、
// 验证码等字段打码，channel_extra 内的同名字段也会打码。
func SanitizeForLog(params map[string]string) map[string]interface{} {
	out := make(map[string]interface{}, len(params))
	for k, v := range params {
		switch {
		case sensitiveLogKeys[k]:
			out[k] = maskValue(v)
		case k == "channel_extra":
			if decoded, err := decodeJSON([]byte(v)); err == nil {
				out[k] = scrub(decoded)
			} else {
				out[k] = "[invalid-json]"
			}
		default:
			out[k] = v
		}
	}
	return out
}

func sanitizeResponse(body map[string]interface{}) interface{} {
	copied := make(map[string]interface{}, len(body))
	for k, v := range body {
		if s, ok := v.(string); ok && k == "data" {
			if decoded, err := decodeJSON([]byte(s)); err == nil {
				v = decoded
			}
		}
		copied[k] = v
	}
	return scrub(copied)
}

func scrub(v interface{}) interface{} {
	switch t := v.(type) {
	case map[string]interface{}:
		out := make(map[string]interface{}, len(t))
		for k, item := range t {
			if sensitiveLogKeys[k] {
				s, ok := scalarString(item)
				if !ok {
					s = "[object]"
				}
				out[k] = maskValue(s)
			} else {
				out[k] = scrub(item)
			}
		}
		return out
	case []interface{}:
		out := make([]interface{}, len(t))
		for i, item := range t {
			out[i] = scrub(item)
		}
		return out
	}
	return v
}

func maskValue(s string) string {
	if utf8.RuneCountInString(s) <= 4 {
		return "****"
	}
	runes := []rune(s)
	return string(runes[:2]) + "****" + string(runes[len(runes)-2:])
}
