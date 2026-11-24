package lspay

import (
	"strconv"
	"strings"
)

// YuanToFen 把元金额字符串（最多 2 位小数，如 "99.99"）换算为分，不经过浮点数。
func YuanToFen(yuan string) (int64, error) {
	text := strings.TrimSpace(yuan)
	if !yuanPattern.MatchString(text) {
		return 0, validationError("非法金额: %s", yuan)
	}
	negative := strings.HasPrefix(text, "-")
	text = strings.TrimPrefix(text, "-")
	intPart, decPart := text, ""
	if i := strings.IndexByte(text, '.'); i >= 0 {
		intPart, decPart = text[:i], text[i+1:]
	}
	decPart = (decPart + "00")[:2]
	fen, err := strconv.ParseInt(intPart+decPart, 10, 64)
	if err != nil {
		return 0, validationError("金额超出范围: %s", yuan)
	}
	if negative {
		fen = -fen
	}
	return fen, nil
}

// FenToYuan 把分换算为保留 2 位小数的元字符串。
func FenToYuan(fen int64) string {
	sign := ""
	if fen < 0 {
		sign = "-"
		fen = -fen
	}
	cents := fen % 100
	s := strconv.FormatInt(cents, 10)
	if cents < 10 {
		s = "0" + s
	}
	return sign + strconv.FormatInt(fen/100, 10) + "." + s
}
