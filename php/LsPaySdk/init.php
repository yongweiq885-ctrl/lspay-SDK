<?php
/**
 * 立收通 (LSPay) SDK 入口
 *
 * 使用方法：
 *   define("DEBUG", true);                              // 可选：调试日志
 *   define("LOG", __DIR__ . "/log");                    // 可选：日志目录
 *   define("PROD_MODE", true);                          // 可选：true=生产 / false=沙箱
 *
 *   require_once __DIR__ . "/LsPaySdk/init.php";
 *
 *   LsPay::init(__DIR__ . "/config/config.json");                       // 默认商户
 *   LsPay::init(__DIR__ . "/config/config_b.json", false, "merch_b");   // 多商户
 */

namespace LsPaySdk;

require_once __DIR__ . '/bootstrap.php';

# 核心类加载
require_once SDK_BASE . "/exceptions/LsPayException.php";
require_once SDK_BASE . "/config/MerConfig.php";
require_once SDK_BASE . "/enums/WayCodeEnum.php";
require_once SDK_BASE . "/enums/PayDataTypeEnum.php";
require_once SDK_BASE . "/enums/OrderStateEnum.php";
require_once SDK_BASE . "/enums/RefundStateEnum.php";
require_once SDK_BASE . "/enums/DelayTransTaskStatusEnum.php";
require_once SDK_BASE . "/enums/QuickPayTransStatusEnum.php";
require_once SDK_BASE . "/enums/ElectronicAccountTypeEnum.php";
require_once SDK_BASE . "/enums/ElectronicAccountRelationTypeEnum.php";
require_once SDK_BASE . "/enums/ElectronicAccountTransStatusEnum.php";
require_once SDK_BASE . "/enums/ElectronicAccountChannelStatusEnum.php";
require_once SDK_BASE . "/enums/ElectronicAccountWithdrawStatusEnum.php";
require_once SDK_BASE . "/enums/ElectronicAccountBalanceStatusEnum.php";
require_once SDK_BASE . "/enums/EndpointEnum.php";
require_once SDK_BASE . "/core/LsPayTools.php";
require_once SDK_BASE . "/core/LsPay.php";
require_once SDK_BASE . "/core/LsPayClient.php";
require_once SDK_BASE . "/core/LsPayResponse.php";
require_once SDK_BASE . "/response/UnifiedOrderResponse.php";
require_once SDK_BASE . "/response/BalancePayResponse.php";
require_once SDK_BASE . "/response/BalanceQueryResponse.php";
require_once SDK_BASE . "/response/QueryOrderResponse.php";
require_once SDK_BASE . "/response/RefundResponse.php";
require_once SDK_BASE . "/response/QueryRefundResponse.php";
require_once SDK_BASE . "/response/DelayTransConfirmResponse.php";
require_once SDK_BASE . "/response/DelayTransConfirmRefundResponse.php";
require_once SDK_BASE . "/response/DelayTransUnconfirmQueryResponse.php";
require_once SDK_BASE . "/response/DelayTransCreateTaskResponse.php";
require_once SDK_BASE . "/response/QuickPayResponse.php";
require_once SDK_BASE . "/response/QuickPayBindApplyResponse.php";
require_once SDK_BASE . "/response/QuickPayBindConfirmResponse.php";
require_once SDK_BASE . "/response/QuickPayApplyResponse.php";
require_once SDK_BASE . "/response/QuickPayConfirmResponse.php";
require_once SDK_BASE . "/response/QuickPayUnbindResponse.php";
require_once SDK_BASE . "/response/QuickPaySmsCheckResponse.php";
require_once SDK_BASE . "/response/ElectronicAccountResponse.php";
require_once SDK_BASE . "/response/ElectronicAccountUserOpenResponse.php";
require_once SDK_BASE . "/response/ElectronicAccountBusinessOpenResponse.php";
require_once SDK_BASE . "/response/ElectronicAccountBusinessChangeResponse.php";
require_once SDK_BASE . "/response/ElectronicAccountUserQueryResponse.php";
require_once SDK_BASE . "/response/ElectronicAccountSettlementQueryResponse.php";
require_once SDK_BASE . "/response/ElectronicAccountBalanceWithdrawResponse.php";
require_once SDK_BASE . "/response/ElectronicAccountSingleWithdrawQueryResponse.php";
require_once SDK_BASE . "/response/ElectronicAccountBatchWithdrawQueryResponse.php";
require_once SDK_BASE . "/notify/BaseNotify.php";
require_once SDK_BASE . "/notify/PaymentNotify.php";
require_once SDK_BASE . "/notify/RefundNotify.php";
require_once SDK_BASE . "/notify/DelayTransTransferNotify.php";
require_once SDK_BASE . "/notify/LsPayNotify.php";
require_once SDK_BASE . "/request/BaseRequest.php";
require_once SDK_BASE . "/request/DivisionReceiver.php";
require_once SDK_BASE . "/request/DivisionChannelExtra.php";
require_once SDK_BASE . "/request/BalancePayChannelExtra.php";
require_once SDK_BASE . "/request/UnifiedOrderChannelExtra.php";
require_once SDK_BASE . "/request/UnifiedOrderRequest.php";
require_once SDK_BASE . "/request/BalancePayRequest.php";
require_once SDK_BASE . "/request/BalanceQueryRequest.php";
require_once SDK_BASE . "/request/QueryOrderRequest.php";
require_once SDK_BASE . "/request/RefundRequest.php";
require_once SDK_BASE . "/request/QueryRefundRequest.php";
require_once SDK_BASE . "/request/DelayTransConfirmRequest.php";
require_once SDK_BASE . "/request/DelayTransConfirmRefundRequest.php";
require_once SDK_BASE . "/request/DelayTransUnconfirmQueryRequest.php";
require_once SDK_BASE . "/request/DelayTransCreateTaskRequest.php";
require_once SDK_BASE . "/request/QuickPayBindChannelExtra.php";
require_once SDK_BASE . "/request/QuickPayBindApplyRequest.php";
require_once SDK_BASE . "/request/QuickPayBindConfirmChannelExtra.php";
require_once SDK_BASE . "/request/QuickPayBindConfirmRequest.php";
require_once SDK_BASE . "/request/QuickPayApplyRequest.php";
require_once SDK_BASE . "/request/QuickPayConfirmRequest.php";
require_once SDK_BASE . "/request/QuickPayUnbindChannelExtra.php";
require_once SDK_BASE . "/request/QuickPayUnbindRequest.php";
require_once SDK_BASE . "/request/QuickPaySmsCheckRequest.php";
require_once SDK_BASE . "/request/ElectronicAccountUserInfo.php";
require_once SDK_BASE . "/request/ElectronicAccountMerchantInfo.php";
require_once SDK_BASE . "/request/ElectronicAccountOpenChannelExtra.php";
require_once SDK_BASE . "/request/ElectronicAccountUserOpenRequest.php";
require_once SDK_BASE . "/request/ElectronicAccountSettlementCard.php";
require_once SDK_BASE . "/request/ElectronicAccountSettleConfig.php";
require_once SDK_BASE . "/request/ElectronicAccountCashConfig.php";
require_once SDK_BASE . "/request/ElectronicAccountBusinessChannelExtra.php";
require_once SDK_BASE . "/request/ElectronicAccountBusinessOpenRequest.php";
require_once SDK_BASE . "/request/ElectronicAccountModifiedCard.php";
require_once SDK_BASE . "/request/ElectronicAccountBusinessChangeChannelExtra.php";
require_once SDK_BASE . "/request/ElectronicAccountBusinessChangeRequest.php";
require_once SDK_BASE . "/request/ElectronicAccountUserQueryRequest.php";
require_once SDK_BASE . "/request/ElectronicAccountSettlementQueryRequest.php";
require_once SDK_BASE . "/request/ElectronicAccountBalanceWithdrawRequest.php";
require_once SDK_BASE . "/request/ElectronicAccountSingleWithdrawQueryRequest.php";
require_once SDK_BASE . "/request/ElectronicAccountBatchWithdrawQueryRequest.php";
require_once SDK_BASE . "/request/RequestFactory.php";
