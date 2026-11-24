<?php
/**
 * 数组兼容调用的集中式 Request 工厂。
 *
 * 新增接口只需在 definitions() 增加端点、请求类与字段 setter 映射，
 * LsPayClient 不再维护端点白名单和多层 if/elseif。
 */
namespace LsPaySdk\request;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;

final class RequestFactory
{
    /**
     * @return BaseRequest|null 未注册端点返回 null，保留原始数组扩展能力。
     */
    public static function fromArray($funcCode, array $params)
    {
        $definition = self::definitionFor((string)$funcCode, $params);
        if ($definition === null) {
            return null;
        }

        $class = $definition['class'];
        /** @var BaseRequest $request */
        $request = new $class();
        foreach ($definition['fields'] as $field => $setter) {
            if (array_key_exists($field, $params)) {
                $request->$setter($params[$field]);
                unset($params[$field]);
            }
        }
        if ($params) {
            $request->setExtendInfo($params);
        }
        return $request;
    }

    public static function supports($funcCode)
    {
        return array_key_exists((string)$funcCode, self::definitions());
    }

    private static function definitionFor($funcCode, array $params)
    {
        $definitions = self::definitions();
        if (!isset($definitions[$funcCode])) {
            return null;
        }
        $definition = $definitions[$funcCode];
        if ($funcCode === EndpointEnum::UNIFIED_ORDER) {
            $wayCode = isset($params['way_code']) ? (string)$params['way_code'] : '';
            if ($wayCode === WayCodeEnum::BANK_FAST) {
                $definition['class'] = QuickPayApplyRequest::class;
            } elseif ($wayCode === WayCodeEnum::BALANCE_PAY) {
                $definition['class'] = BalancePayRequest::class;
            }
        }
        return $definition;
    }

    private static function definitions()
    {
        $unifiedFields = [
            'mch_order_no' => 'setMchOrderNo',
            'way_code' => 'setWayCode',
            'amount' => 'setAmount',
            'currency' => 'setCurrency',
            'subject' => 'setSubject',
            'body' => 'setBody',
            'channel_extra' => 'setChannelExtra',
            'client_ip' => 'setClientIp',
            'notify_url' => 'setNotifyUrl',
            'return_url' => 'setReturnUrl',
            'expired_time' => 'setExpiredTime',
            'division_mode' => 'setDivisionMode',
            'ext_param' => 'setExtParam',
            'polling_mode' => 'setPollingMode',
        ];

        return [
            EndpointEnum::UNIFIED_ORDER => [
                'class' => UnifiedOrderRequest::class,
                'fields' => $unifiedFields,
            ],
            EndpointEnum::QUERY_ORDER => [
                'class' => QueryOrderRequest::class,
                'fields' => [
                    'mch_order_no' => 'setMchOrderNo',
                    'pay_order_id' => 'setPayOrderId',
                ],
            ],
            EndpointEnum::REFUND => [
                'class' => RefundRequest::class,
                'fields' => [
                    'mch_refund_no' => 'setMchRefundNo',
                    'mch_order_no' => 'setMchOrderNo',
                    'pay_order_id' => 'setPayOrderId',
                    'refund_amount' => 'setRefundAmount',
                    'currency' => 'setCurrency',
                    'refund_reason' => 'setRefundReason',
                    'client_ip' => 'setClientIp',
                    'notify_url' => 'setNotifyUrl',
                    'channel_extra' => 'setChannelExtra',
                    'ext_param' => 'setExtParam',
                ],
            ],
            EndpointEnum::QUERY_REFUND => [
                'class' => QueryRefundRequest::class,
                'fields' => [
                    'mch_refund_no' => 'setMchRefundNo',
                    'order_refund_id' => 'setOrderRefundId',
                ],
            ],
            EndpointEnum::DELAY_TRANS_CONFIRM => [
                'class' => DelayTransConfirmRequest::class,
                'fields' => [
                    'pay_order_id' => 'setPayOrderId',
                    'mch_order_no' => 'setMchOrderNo',
                    'order_id' => 'setOrderId',
                    'channel_extra' => 'setChannelExtra',
                ],
            ],
            EndpointEnum::DELAY_TRANS_CONFIRM_REFUND => [
                'class' => DelayTransConfirmRefundRequest::class,
                'fields' => [
                    'confirm_order_id' => 'setConfirmOrderId',
                    'confirm_refund_no' => 'setConfirmRefundNo',
                    'channel_extra' => 'setChannelExtra',
                ],
            ],
            EndpointEnum::DELAY_TRANS_UNCONFIRM_QUERY => [
                'class' => DelayTransUnconfirmQueryRequest::class,
                'fields' => [],
            ],
            EndpointEnum::DELAY_TRANS_CREATE_TASK => [
                'class' => DelayTransCreateTaskRequest::class,
                'fields' => [
                    'account_no' => 'setAccountNo',
                    'account_name' => 'setAccountName',
                    'order_no' => 'setOrderNo',
                    'amount' => 'setAmount',
                    'notify_url' => 'setNotifyUrl',
                    'remark' => 'setRemark',
                ],
            ],
            EndpointEnum::QUICK_PAY_BIND_APPLY => [
                'class' => QuickPayBindApplyRequest::class,
                'fields' => [
                    'order_id' => 'setOrderId',
                    'way_code' => 'setWayCode',
                    'client_ip' => 'setClientIp',
                    'channel_extra' => 'setChannelExtra',
                ],
            ],
            EndpointEnum::QUICK_PAY_BIND_CONFIRM => [
                'class' => QuickPayBindConfirmRequest::class,
                'fields' => [
                    'order_id' => 'setOrderId',
                    'way_code' => 'setWayCode',
                    'client_ip' => 'setClientIp',
                    'channel_extra' => 'setChannelExtra',
                    'verify_code' => 'setVerifyCode',
                ],
            ],
            EndpointEnum::QUICK_PAY_CONFIRM => [
                'class' => QuickPayConfirmRequest::class,
                'fields' => self::quickPayConfirmationFields(),
            ],
            EndpointEnum::QUICK_PAY_UNBIND => [
                'class' => QuickPayUnbindRequest::class,
                'fields' => [
                    'order_id' => 'setOrderId',
                    'way_code' => 'setWayCode',
                    'client_ip' => 'setClientIp',
                    'channel_extra' => 'setChannelExtra',
                ],
            ],
            EndpointEnum::QUICK_PAY_SMS_CHECK => [
                'class' => QuickPaySmsCheckRequest::class,
                'fields' => self::quickPayConfirmationFields(),
            ],
            EndpointEnum::BALANCE_QUERY => [
                'class' => BalanceQueryRequest::class,
                'fields' => [
                    'order_id' => 'setOrderId',
                    'way_code' => 'setWayCode',
                    'client_ip' => 'setClientIp',
                    'account_no' => 'setAccountNo',
                ],
            ],
            EndpointEnum::ELECTRONIC_ACCOUNT_USER_OPEN => [
                'class' => ElectronicAccountUserOpenRequest::class,
                'fields' => [
                    'way_code' => 'setWayCode',
                    'order_id' => 'setOrderId',
                    'account_name' => 'setAccountName',
                    'account_type' => 'setAccountType',
                    'relation_type' => 'setRelationType',
                    'relation_type_name' => 'setRelationTypeName',
                    'account_alias' => 'setAccountAlias',
                    'division_profit' => 'setDivisionProfit',
                    'client_ip' => 'setClientIp',
                    'channel_extra' => 'setChannelExtra',
                ],
            ],
            EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_OPEN => [
                'class' => ElectronicAccountBusinessOpenRequest::class,
                'fields' => ['way_code' => 'setWayCode'] + self::electronicAccountBusinessFields(),
            ],
            EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_CHANGE => [
                'class' => ElectronicAccountBusinessChangeRequest::class,
                'fields' => ['way_code' => 'setWayCode'] + self::electronicAccountBusinessFields(),
            ],
            EndpointEnum::ELECTRONIC_ACCOUNT_USER_QUERY => [
                'class' => ElectronicAccountUserQueryRequest::class,
                'fields' => [
                    'way_code' => 'setWayCode',
                    'account_no' => 'setAccountNo',
                    'client_ip' => 'setClientIp',
                ],
            ],
            EndpointEnum::ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY => [
                'class' => ElectronicAccountSettlementQueryRequest::class,
                'fields' => [
                    'order_id' => 'setOrderId',
                    'way_code' => 'setWayCode',
                    'account_no' => 'setAccountNo',
                    'begin_date' => 'setBeginDate',
                    'end_date' => 'setEndDate',
                    'page_size' => 'setPageSize',
                    'page_num' => 'setPageNum',
                ],
            ],
            EndpointEnum::ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW => [
                'class' => ElectronicAccountBalanceWithdrawRequest::class,
                'fields' => [
                    'order_id' => 'setOrderId',
                    'way_code' => 'setWayCode',
                    'account_no' => 'setAccountNo',
                    'withdraw_amount' => 'setWithdrawAmount',
                ],
            ],
            EndpointEnum::ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY => [
                'class' => ElectronicAccountSingleWithdrawQueryRequest::class,
                'fields' => [
                    'way_code' => 'setWayCode',
                    'account_no' => 'setAccountNo',
                    'withdraw_date' => 'setWithdrawDate',
                    'order_id' => 'setOrderId',
                ],
            ],
            EndpointEnum::ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY => [
                'class' => ElectronicAccountBatchWithdrawQueryRequest::class,
                'fields' => [
                    'way_code' => 'setWayCode',
                    'account_no' => 'setAccountNo',
                    'begin_date' => 'setBeginDate',
                    'end_date' => 'setEndDate',
                    'page_size' => 'setPageSize',
                    'page_num' => 'setPageNum',
                ],
            ],
        ];
    }

    private static function quickPayConfirmationFields()
    {
        return [
            'way_code' => 'setWayCode',
            'client_ip' => 'setClientIp',
            'pay_order_id' => 'setPayOrderId',
            'mch_order_no' => 'setMchOrderNo',
            'verify_code' => 'setVerifyCode',
        ];
    }

    private static function electronicAccountBusinessFields()
    {
        return [
            'order_id' => 'setOrderId',
            'account_no' => 'setAccountNo',
            'client_ip' => 'setClientIp',
            'channel_extra' => 'setChannelExtra',
        ];
    }
}
