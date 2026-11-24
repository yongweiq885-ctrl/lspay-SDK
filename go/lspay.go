// Package lspay 是立收通（LSPay）支付网关的 Go SDK。
//
// 每个商户创建一个 Client。请求结构体在发送前校验字段，公共参数和签名由 Client 注入。
// md5_key 只能放在服务端，前端和 App 只调用业务方自己的后端。
package lspay

// Version 是 SDK 版本号。
const Version = "3.0.0"

// 网关地址。
const (
	DefaultBaseURL = "https://api.lspay.tech/"
	SandboxBaseURL = "https://sandbox.lspay.tech/"
)

// 每个请求都会带上的公共参数，业务参数不能覆盖。
const (
	apiVersion = "1.0"
	signType   = "MD5"
)

var reservedParams = []string{"mch_no", "app_id", "req_time", "version", "sign_type", "sign"}
