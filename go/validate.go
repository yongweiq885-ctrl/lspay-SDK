package lspay

import (
	"net"
	"net/url"
	"regexp"
	"strings"
	"time"
	"unicode/utf8"
)

var (
	digitsPattern         = regexp.MustCompile(`^\d+$`)
	region6Pattern        = regexp.MustCompile(`^\d{6}$`)
	emailPattern          = regexp.MustCompile(`^[^@\s]+@[^@\s]+\.[^@\s]+$`)
	currencyPattern       = regexp.MustCompile(`^[A-Za-z]{3}$`)
	md5Pattern            = regexp.MustCompile(`^[A-Fa-f0-9]{32}$`)
	expirationPattern     = regexp.MustCompile(`^(0[1-9]|1[0-2])\d{2}$`)
	divisionProfitPattern = regexp.MustCompile(`^(?:0(?:\.\d+)?|1(?:\.0+)?)$`)
	withdrawAmountPattern = regexp.MustCompile(`^(?:0|[1-9]\d*)(?:\.\d{1,2})?$`)
	yuanPattern           = regexp.MustCompile(`^-?(?:0|[1-9]\d*)(?:\.\d{1,2})?$`)
)

func textLen(s string) int { return utf8.RuneCountInString(s) }

func requiredString(field, value string, max int) error {
	if strings.TrimSpace(value) == "" {
		return validationError("%s 必填", field)
	}
	if textLen(value) > max {
		return validationError("%s 长度不能超过 %d", field, max)
	}
	return nil
}

func optionalString(field, value string, max int) error {
	if textLen(value) > max {
		return validationError("%s 长度不能超过 %d", field, max)
	}
	return nil
}

func requireOne(aField, a, bField, b string, max int) error {
	if a == "" && b == "" {
		return validationError("%s 与 %s 至少填写一个", aField, bField)
	}
	if err := optionalString(aField, a, max); err != nil {
		return err
	}
	return optionalString(bField, b, max)
}

func checkIPv4(field, value string) error {
	if value == "" {
		return nil
	}
	ip := net.ParseIP(value)
	if ip == nil || ip.To4() == nil || strings.Contains(value, ":") {
		return validationError("%s 必须是合法 IPv4 地址", field)
	}
	return nil
}

func checkHTTPURL(field, value string, max int) error {
	if value == "" {
		return nil
	}
	if err := optionalString(field, value, max); err != nil {
		return err
	}
	u, err := url.Parse(value)
	if err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Host == "" {
		return validationError("%s 必须是合法 HTTP(S) URL", field)
	}
	return nil
}

func parseDate8(field, value string) (time.Time, error) {
	if len(value) != 8 || !digitsPattern.MatchString(value) {
		return time.Time{}, validationError("%s 必须是合法的 yyyyMMdd 格式日期", field)
	}
	t, err := time.Parse("20060102", value)
	if err != nil {
		return time.Time{}, validationError("%s 必须是合法的 yyyyMMdd 格式日期", field)
	}
	return t, nil
}

func checkDateTime(field, value string) error {
	const layout = "2006-01-02 15:04:05"
	t, err := time.Parse(layout, value)
	if err != nil || t.Format(layout) != value {
		return validationError("%s 必须是 yyyy-MM-dd HH:mm:ss 格式", field)
	}
	return nil
}

func normalizeCurrency(value string) (string, error) {
	if value == "" {
		return "cny", nil
	}
	if !currencyPattern.MatchString(value) {
		return "", validationError("currency 必须是 3 位字母货币代码")
	}
	return strings.ToLower(value), nil
}

func checkRange(field string, value int, min, max int) error {
	if value < min || value > max {
		return validationError("%s 只允许 %d 到 %d", field, min, max)
	}
	return nil
}
