package lspay

import (
	"sort"
	"strconv"
)

// Request 是所有接口请求的公共接口。
type Request interface {
	// Endpoint 返回接口路径。
	Endpoint() string
	// Params 校验字段并返回待签名的业务参数，不含公共参数。
	Params() (map[string]string, error)
}

type params map[string]string

func (p params) set(key, value string) {
	if value != "" {
		p[key] = value
	}
}

func (p params) setInt(key string, value int64) {
	if value != 0 {
		p[key] = strconv.FormatInt(value, 10)
	}
}

// finish 合并 ExtendInfo。扩展参数用于平台新增、SDK 尚未声明的字段，不能覆盖已声明字段。
func (p params) finish(extend map[string]string) (map[string]string, error) {
	keys := make([]string, 0, len(extend))
	for k := range extend {
		keys = append(keys, k)
	}
	sort.Strings(keys)
	for _, k := range keys {
		if _, exists := p[k]; exists {
			return nil, validationError("扩展参数不允许覆盖请求字段: %s", k)
		}
		p.set(k, extend[k])
	}
	return p, nil
}

func validate(r Request) error {
	_, err := r.Params()
	return err
}
