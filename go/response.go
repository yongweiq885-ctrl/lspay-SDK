package lspay

import (
	"fmt"
	"strings"
)

// Response 是网关的原始响应。外层 status == 200 只表示接口调用成功，
// 业务结果看 data 里的 trans_status、order_state、state，由各类型化响应提供判断方法。
type Response struct {
	Raw  []byte
	body map[string]interface{}
}

// ParseResponse 解析网关响应正文。正文不是 JSON 对象时返回协议错误。
func ParseResponse(raw []byte) (*Response, error) {
	v, err := decodeJSON(raw)
	body, ok := v.(map[string]interface{})
	if err != nil || !ok {
		return nil, &Error{Kind: ErrKindProtocol, Message: "立收通响应不是合法 JSON 对象", Err: err}
	}
	return &Response{Raw: append([]byte(nil), raw...), body: body}, nil
}

// Map 返回解析后的整个响应。数字为 json.Number。
func (r *Response) Map() map[string]interface{} { return r.body }

// Status 返回外层 status（兼容 code、retCode）。
func (r *Response) Status() string {
	for _, key := range []string{"status", "code", "retCode"} {
		if s, ok := scalarString(r.body[key]); ok {
			return s
		}
	}
	return ""
}

// Msg 返回外层 msg（兼容 retMsg）。
func (r *Response) Msg() string {
	for _, key := range []string{"msg", "retMsg"} {
		if s, ok := scalarString(r.body[key]); ok {
			return s
		}
	}
	return ""
}

// Sign 返回同步响应里的 sign。同步响应不需要验签。
func (r *Response) Sign() string { return objectString(r.body, "sign") }

// IsError 判断接口调用是否失败。
func (r *Response) IsError() bool {
	switch strings.ToUpper(r.Status()) {
	case "200", "0", "SUCCESS":
		return false
	}
	return true
}

// ErrorInfo 返回 "[status] msg" 形式的错误描述。
func (r *Response) ErrorInfo() string { return fmt.Sprintf("[%s] %s", r.Status(), r.Msg()) }

// Err 在 IsError 为 true 时返回 ErrKindBiz 错误，否则返回 nil。
func (r *Response) Err() error {
	if !r.IsError() {
		return nil
	}
	return &Error{Kind: ErrKindBiz, Message: r.ErrorInfo()}
}

// Data 返回 data 对象。data 是 JSON 字符串时先解析；不是对象时返回空 map。
func (r *Response) Data() map[string]interface{} {
	switch d := r.body["data"].(type) {
	case map[string]interface{}:
		return d
	case string:
		if m, err := decodeJSONObject([]byte(d)); err == nil && m != nil {
			return m
		}
	}
	return map[string]interface{}{}
}

func (r *Response) rawData() interface{} {
	d := r.body["data"]
	if s, ok := d.(string); ok {
		if v, err := decodeJSON([]byte(s)); err == nil {
			return v
		}
	}
	return d
}

// Value 先在 data 里找 key，找不到再看外层。部分接口不带 data 外层，直接返回业务字段。
func (r *Response) Value(key string) interface{} {
	if v, ok := r.Data()[key]; ok {
		return v
	}
	return r.body[key]
}

// String 返回业务字段的字符串形式，不存在时为 ""。
func (r *Response) String(key string) string {
	s, _ := scalarString(r.Value(key))
	return s
}

// Int 返回业务字段的整数值，第二个返回值表示字段存在且是整数。
func (r *Response) Int(key string) (int64, bool) { return looseInt(r.Value(key)) }

func (r *Response) hasEnvelope() bool {
	for _, key := range []string{"status", "code", "retCode"} {
		if _, ok := r.body[key]; ok {
			return true
		}
	}
	return false
}

func (r *Response) intOrZero(key string) int64 {
	n, _ := r.Int(key)
	return n
}

func (r *Response) state(key string) (int, bool) {
	n, ok := r.Int(key)
	return int(n), ok
}

func firstNonEmpty(r *Response, keys ...string) string {
	for _, key := range keys {
		if s := r.String(key); s != "" {
			return s
		}
	}
	return ""
}
