package lspay

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"strconv"
	"strings"
)

// marshalJSON 输出紧凑 JSON，不转义 <、>、&。
func marshalJSON(v interface{}) ([]byte, error) {
	var buf bytes.Buffer
	enc := json.NewEncoder(&buf)
	enc.SetEscapeHTML(false)
	if err := enc.Encode(v); err != nil {
		return nil, err
	}
	return bytes.TrimRight(buf.Bytes(), "\n"), nil
}

// decodeJSON 解析单个 JSON 值。数字保留为 json.Number，避免整数变成浮点数。
func decodeJSON(raw []byte) (interface{}, error) {
	dec := json.NewDecoder(bytes.NewReader(raw))
	dec.UseNumber()
	var v interface{}
	if err := dec.Decode(&v); err != nil {
		return nil, err
	}
	if _, err := dec.Token(); err != io.EOF {
		return nil, fmt.Errorf("JSON 之后存在多余内容")
	}
	return v, nil
}

func decodeJSONObject(raw []byte) (map[string]interface{}, error) {
	v, err := decodeJSON(raw)
	if err != nil {
		return nil, err
	}
	if v == nil {
		return nil, nil
	}
	m, ok := v.(map[string]interface{})
	if !ok {
		return nil, fmt.Errorf("不是 JSON 对象")
	}
	return m, nil
}

// normalizeJSONObject 把构造器、map 或 JSON 对象字符串统一成紧凑 JSON。
// 返回的字符串既用于签名又用于发送；map 供校验使用。value 为空时返回 ""。
func normalizeJSONObject(field string, value interface{}) (string, map[string]interface{}, error) {
	var raw []byte
	switch v := value.(type) {
	case nil:
		return "", nil, nil
	case string:
		if strings.TrimSpace(v) == "" {
			return "", nil, nil
		}
		raw = []byte(v)
	case []byte:
		raw = v
	case json.RawMessage:
		raw = v
	default:
		b, err := marshalJSON(v)
		if err != nil {
			return "", nil, &Error{Kind: ErrKindValidation, Message: field + " JSON 编码失败", Err: err}
		}
		raw = b
	}
	m, err := decodeJSONObject(raw)
	if err != nil {
		return "", nil, validationError("%s 必须是合法 JSON 对象", field)
	}
	if m == nil {
		return "", nil, nil
	}
	encoded, err := marshalJSON(m)
	if err != nil {
		return "", nil, &Error{Kind: ErrKindValidation, Message: field + " JSON 编码失败", Err: err}
	}
	return string(encoded), m, nil
}

// scalarString 把 JSON 标量转成字符串；对象、数组和 null 返回 false。
func scalarString(v interface{}) (string, bool) {
	switch t := v.(type) {
	case string:
		return t, true
	case json.Number:
		return t.String(), true
	case bool:
		return strconv.FormatBool(t), true
	}
	return "", false
}

// strictInt 只接受 JSON 整数。
func strictInt(v interface{}) (int64, bool) {
	n, ok := v.(json.Number)
	if !ok {
		return 0, false
	}
	i, err := strconv.ParseInt(string(n), 10, 64)
	return i, err == nil
}

// looseInt 接受 JSON 整数或整数字符串。
func looseInt(v interface{}) (int64, bool) {
	if i, ok := strictInt(v); ok {
		return i, true
	}
	s, ok := v.(string)
	if !ok {
		return 0, false
	}
	i, err := strconv.ParseInt(strings.TrimSpace(s), 10, 64)
	return i, err == nil
}

func objectString(m map[string]interface{}, key string) string {
	s, _ := scalarString(m[key])
	return s
}

func requiredField(m map[string]interface{}, key, path string, max int) (string, error) {
	s, ok := scalarString(m[key])
	if !ok || strings.TrimSpace(s) == "" {
		return "", validationError("%s 必填", path)
	}
	if textLen(s) > max {
		return "", validationError("%s 长度不能超过 %d", path, max)
	}
	return s, nil
}

func requiredFields(m map[string]interface{}, path string, keys ...string) error {
	for _, key := range keys {
		if _, err := requiredField(m, key, path+"."+key, 256); err != nil {
			return err
		}
	}
	return nil
}

func objectList(v interface{}) []map[string]interface{} {
	list, _ := v.([]interface{})
	out := make([]map[string]interface{}, 0, len(list))
	for _, item := range list {
		if m, ok := item.(map[string]interface{}); ok {
			out = append(out, m)
		}
	}
	return out
}
