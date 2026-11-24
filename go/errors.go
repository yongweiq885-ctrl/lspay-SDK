package lspay

import (
	"errors"
	"fmt"
)

// ErrorKind 区分错误来源。
type ErrorKind string

const (
	ErrKindConfig     ErrorKind = "config"     // 商户配置缺失或非法
	ErrKindValidation ErrorKind = "validation" // 请求或通知字段不符合协议
	ErrKindNetwork    ErrorKind = "network"    // 连接失败、超时或非 2xx HTTP 状态
	ErrKindProtocol   ErrorKind = "protocol"   // 响应不是合法 JSON 对象
	ErrKindSign       ErrorKind = "sign"       // 通知签名缺失或错误
	ErrKindBiz        ErrorKind = "biz"        // 网关返回的业务错误，由 Response.Err 产生
)

// Error 是 SDK 返回的所有错误的类型。
type Error struct {
	Kind    ErrorKind
	Message string
	// HTTPStatus 只在网络错误时有值，连接失败时为 0。
	HTTPStatus int
	Err        error
}

func (e *Error) Error() string {
	if e.Err != nil {
		return fmt.Sprintf("lspay %s: %s: %v", e.Kind, e.Message, e.Err)
	}
	return fmt.Sprintf("lspay %s: %s", e.Kind, e.Message)
}

func (e *Error) Unwrap() error { return e.Err }

// IsKind 判断 err 是否为指定类型的 SDK 错误。
func IsKind(err error, kind ErrorKind) bool {
	var e *Error
	return errors.As(err, &e) && e.Kind == kind
}

func newError(kind ErrorKind, format string, args ...interface{}) *Error {
	return &Error{Kind: kind, Message: fmt.Sprintf(format, args...)}
}

func validationError(format string, args ...interface{}) error {
	return newError(ErrKindValidation, format, args...)
}

func configError(format string, args ...interface{}) error {
	return newError(ErrKindConfig, format, args...)
}

func signError(format string, args ...interface{}) error {
	return newError(ErrKindSign, format, args...)
}
