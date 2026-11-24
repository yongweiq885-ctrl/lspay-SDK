package lspay

// channel_extra.pay_data_type 取值，决定 pay_info 的格式。
const (
	PayDataTypeCashier    = "cashier"      // 立收通收银台，form 表单提交
	PayDataTypePayURL     = "pay_url"      // 支付链接
	PayDataTypeWxApp      = "wxapp"        // 微信原生支付参数
	PayDataTypeAliApp     = "aliapp"       // 支付宝 App SDK 参数
	PayDataTypeYsfApp     = "ysfapp"       // 云闪付 App SDK 参数
	PayDataTypeCodeURL    = "code_url"     // 二维码内容，需自行渲染
	PayDataTypeCodeImgURL = "code_img_url" // 二维码图片地址
	PayDataTypeNone       = "none"         // 无需前端操作，如付款码支付
)

var payDataTypes = map[string]bool{
	PayDataTypeCashier: true, PayDataTypePayURL: true, PayDataTypeWxApp: true,
	PayDataTypeAliApp: true, PayDataTypeYsfApp: true, PayDataTypeCodeURL: true,
	PayDataTypeCodeImgURL: true, PayDataTypeNone: true,
}

// 支付订单状态（统一下单的 order_state、订单查询和支付通知的 state）。
const (
	OrderStateCreated  = 0
	OrderStatePaying   = 1
	OrderStatePaid     = 2
	OrderStateFailed   = 3
	OrderStateRevoked  = 4
	OrderStateRefunded = 5
	OrderStateClosed   = 6
)

// 退款状态。
const (
	RefundStateCreated   = 0
	RefundStateRefunding = 1
	RefundStateSucceeded = 2
	RefundStateFailed    = 3
	RefundStateClosed    = 4
)

// trans_status 取值。
const (
	TransStatusSucceeded  = "S"
	TransStatusProcessing = "P"
	TransStatusFailed     = "F"
)

// 电子账户类型 account_type。
const (
	AccountTypePersonal           = 1
	AccountTypeEnterprise         = 2
	AccountTypeIndividualBusiness = 3
)

// 电子账户关系类型 relation_type。
const (
	RelationTypeServiceProvider = "SERVICE_PROVIDER"
	RelationTypeStore           = "STORE"
	RelationTypeStaff           = "STAFF"
	RelationTypeStoreOwner      = "STORE_OWNER"
	RelationTypePartner         = "PARTNER"
	RelationTypeHeadquarter     = "HEADQUARTER"
	RelationTypeBrand           = "BRAND"
	RelationTypeDistributor     = "DISTRIBUTOR"
	RelationTypeUser            = "USER"
	RelationTypeSupplier        = "SUPPLIER"
	RelationTypeCustom          = "CUSTOM"
)

var relationTypes = map[string]bool{
	RelationTypeServiceProvider: true, RelationTypeStore: true, RelationTypeStaff: true,
	RelationTypeStoreOwner: true, RelationTypePartner: true, RelationTypeHeadquarter: true,
	RelationTypeBrand: true, RelationTypeDistributor: true, RelationTypeUser: true,
	RelationTypeSupplier: true, RelationTypeCustom: true,
}

// 延时分账订单转账任务状态。
const (
	TaskStatusPending    = "PENDING"
	TaskStatusProcessing = "PROCESSING"
	TaskStatusCompleted  = "COMPLETED"
	TaskStatusFailed     = "FAILED"
	TaskStatusPartial    = "PARTIAL"
)

var taskStatuses = map[string]bool{
	TaskStatusPending: true, TaskStatusProcessing: true, TaskStatusCompleted: true,
	TaskStatusFailed: true, TaskStatusPartial: true,
}
