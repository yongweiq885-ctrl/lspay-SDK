package lspay

import (
	"crypto/md5"
	"crypto/subtle"
	"encoding/hex"
	"sort"
	"strings"
)

// Sign 按立收通规则签名：去掉 sign 和空值，按键名 ASCII 升序拼成 k=v&k=v（值不做 URL 编码），
// 末尾加 &key={md5Key}，取 MD5 大写十六进制。
func Sign(params map[string]string, md5Key string) string {
	keys := make([]string, 0, len(params))
	for k, v := range params {
		if k == "sign" || v == "" {
			continue
		}
		keys = append(keys, k)
	}
	sort.Strings(keys)
	var b strings.Builder
	for _, k := range keys {
		b.WriteString(k)
		b.WriteByte('=')
		b.WriteString(params[k])
		b.WriteByte('&')
	}
	b.WriteString("key=")
	b.WriteString(md5Key)
	sum := md5.Sum([]byte(b.String()))
	return strings.ToUpper(hex.EncodeToString(sum[:]))
}

// VerifySign 用全部字段重新签名，并与 params["sign"] 做常量时间比较。
func VerifySign(params map[string]string, md5Key string) bool {
	got := params["sign"]
	if got == "" {
		return false
	}
	return constantTimeEqual(Sign(params, md5Key), strings.ToUpper(got))
}

func constantTimeEqual(a, b string) bool {
	return subtle.ConstantTimeCompare([]byte(a), []byte(b)) == 1
}
