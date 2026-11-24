<?php
/** 请求客户端。可传 Request 对象或 funcCode 数组。 */
namespace LsPaySdk\core;

use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\exceptions\LsPayValidationException;
use LsPaySdk\request\BalancePayRequest;
use LsPaySdk\request\BalanceQueryRequest;
use LsPaySdk\request\BaseRequest;
use LsPaySdk\request\ElectronicAccountBalanceWithdrawRequest;
use LsPaySdk\request\ElectronicAccountBatchWithdrawQueryRequest;
use LsPaySdk\request\ElectronicAccountBusinessChangeRequest;
use LsPaySdk\request\ElectronicAccountBusinessOpenRequest;
use LsPaySdk\request\ElectronicAccountSettlementQueryRequest;
use LsPaySdk\request\ElectronicAccountSingleWithdrawQueryRequest;
use LsPaySdk\request\ElectronicAccountUserQueryRequest;
use LsPaySdk\request\ElectronicAccountUserOpenRequest;
use LsPaySdk\request\DelayTransCreateTaskRequest;
use LsPaySdk\request\DelayTransConfirmRefundRequest;
use LsPaySdk\request\DelayTransConfirmRequest;
use LsPaySdk\request\DelayTransUnconfirmQueryRequest;
use LsPaySdk\request\QueryOrderRequest;
use LsPaySdk\request\QueryRefundRequest;
use LsPaySdk\request\QuickPayApplyRequest;
use LsPaySdk\request\QuickPayBindApplyRequest;
use LsPaySdk\request\QuickPayBindConfirmRequest;
use LsPaySdk\request\QuickPayConfirmRequest;
use LsPaySdk\request\QuickPaySmsCheckRequest;
use LsPaySdk\request\QuickPayUnbindRequest;
use LsPaySdk\request\RefundRequest;
use LsPaySdk\request\RequestFactory;
use LsPaySdk\request\UnifiedOrderRequest;
use ReflectionClass;

class LsPayClient extends LsPay
{
    /** @var string 当前实例使用的商户配置 key */
    protected $merchantKey = 'default';

    public function __construct($merchantKey = 'default')
    {
        $this->merchantKey = $merchantKey;
    }

    /**
     * 发起接口请求
     *
     * @param BaseRequest|array $request
     * @return LsPayResponse
     */
    public function postRequest($request)
    {
        try {
            if ($request instanceof BaseRequest) {
                $request->validate();
                $data = $this->objectToArray($request);
                $responseClass = $request->getResponseClass();
            } else {
                if (!is_array($request)) {
                    throw new LsPayValidationException('request 必须是 BaseRequest 对象或数组');
                }
                $data = $request;
                $responseClass = LsPayResponse::class;
                if (isset($data['funcCode'])
                    && RequestFactory::supports($data['funcCode'])) {
                    if (isset($data['params']) && !is_array($data['params'])) {
                        throw new LsPayValidationException('params 必须是数组');
                    }
                    $params = isset($data['params']) ? $data['params'] : [];
                    $typedRequest = RequestFactory::fromArray(
                        $data['funcCode'],
                        $params
                    );
                    $typedRequest->validate();
                    $data = $this->objectToArray($typedRequest);
                    $responseClass = $typedRequest->getResponseClass();
                }
            }
            if (empty($data['funcCode'])) {
                throw new LsPayException('funcCode 不能为空');
            }
            $params = isset($data['params']) ? $data['params'] : [];
            return $this->post(
                $data['funcCode'],
                $params,
                $this->merchantKey,
                $responseClass
            );
        } catch (LsPayException $e) {
            self::writeLog('ERROR ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * 生成 pay_data_type=cashier 的收银台表单数据，由用户浏览器 POST 到 action。
     *
     * @return array{action:string,method:string,fields:array}
     */
    public function buildCashierForm(UnifiedOrderRequest $request)
    {
        try {
            $request->validate();
            $data = $this->objectToArray($request);
            if (!self::isCashierOrder($data['params'])) {
                throw new LsPayValidationException('buildCashierForm 仅用于 channel_extra.pay_data_type=cashier');
            }
            list($url, $fields) = $this->buildSignedRequest(
                $data['funcCode'],
                $data['params'],
                $this->merchantKey
            );
            return ['action' => $url, 'method' => 'POST', 'fields' => $fields];
        } catch (LsPayException $e) {
            self::writeLog('ERROR ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /** 生成自动提交到立收通收银台的 HTML 表单。 */
    public function renderCashierForm(UnifiedOrderRequest $request)
    {
        $form = $this->buildCashierForm($request);
        $html = '<form id="lspayCashierForm" method="POST" action="'
            . htmlspecialchars($form['action'], ENT_QUOTES, 'UTF-8') . '">';
        foreach ($form['fields'] as $name => $value) {
            $html .= '<input type="hidden" name="' . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8')
                . '" value="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '">';
        }
        return $html . '</form><script>document.getElementById("lspayCashierForm").submit();</script>';
    }

    private function unifiedOrderFromArray(array $params)
    {
        $wayCode = isset($params['way_code']) ? (string)$params['way_code'] : '';
        if ($wayCode === \LsPaySdk\enums\WayCodeEnum::BANK_FAST) {
            $request = new QuickPayApplyRequest();
        } elseif ($wayCode === \LsPaySdk\enums\WayCodeEnum::BALANCE_PAY) {
            $request = new BalancePayRequest();
        } else {
            $request = new UnifiedOrderRequest();
        }
        $setters = [
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
        foreach ($setters as $field => $setter) {
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

    private function balanceQueryFromArray(array $params)
    {
        $request = new BalanceQueryRequest();
        $setters = [
            'order_id' => 'setOrderId',
            'way_code' => 'setWayCode',
            'client_ip' => 'setClientIp',
            'account_no' => 'setAccountNo',
        ];
        foreach ($setters as $field => $setter) {
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

    private function delayTransConfirmFromArray(array $params)
    {
        $request = new DelayTransConfirmRequest();
        $setters = [
            'pay_order_id' => 'setPayOrderId',
            'mch_order_no' => 'setMchOrderNo',
            'order_id' => 'setOrderId',
            'channel_extra' => 'setChannelExtra',
        ];
        foreach ($setters as $field => $setter) {
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

    private function delayTransConfirmRefundFromArray(array $params)
    {
        $request = new DelayTransConfirmRefundRequest();
        $setters = [
            'confirm_order_id' => 'setConfirmOrderId',
            'confirm_refund_no' => 'setConfirmRefundNo',
            'channel_extra' => 'setChannelExtra',
        ];
        foreach ($setters as $field => $setter) {
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

    private function delayTransUnconfirmQueryFromArray(array $params)
    {
        $request = new DelayTransUnconfirmQueryRequest();
        if ($params) {
            $request->setExtendInfo($params);
        }
        return $request;
    }

    private function delayTransCreateTaskFromArray(array $params)
    {
        $request = new DelayTransCreateTaskRequest();
        $setters = [
            'account_no' => 'setAccountNo',
            'account_name' => 'setAccountName',
            'order_no' => 'setOrderNo',
            'amount' => 'setAmount',
            'notify_url' => 'setNotifyUrl',
            'remark' => 'setRemark',
        ];
        foreach ($setters as $field => $setter) {
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

    private function quickPayBindApplyFromArray(array $params)
    {
        $request = new QuickPayBindApplyRequest();
        $setters = [
            'order_id' => 'setOrderId',
            'way_code' => 'setWayCode',
            'client_ip' => 'setClientIp',
            'channel_extra' => 'setChannelExtra',
        ];
        foreach ($setters as $field => $setter) {
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

    private function quickPayBindConfirmFromArray(array $params)
    {
        $request = new QuickPayBindConfirmRequest();
        $setters = [
            'order_id' => 'setOrderId',
            'way_code' => 'setWayCode',
            'client_ip' => 'setClientIp',
            'channel_extra' => 'setChannelExtra',
            'verify_code' => 'setVerifyCode',
        ];
        foreach ($setters as $field => $setter) {
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

    private function quickPayConfirmFromArray(array $params)
    {
        $request = new QuickPayConfirmRequest();
        $setters = [
            'way_code' => 'setWayCode',
            'client_ip' => 'setClientIp',
            'pay_order_id' => 'setPayOrderId',
            'mch_order_no' => 'setMchOrderNo',
            'verify_code' => 'setVerifyCode',
        ];
        foreach ($setters as $field => $setter) {
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

    private function quickPayUnbindFromArray(array $params)
    {
        $request = new QuickPayUnbindRequest();
        $setters = [
            'order_id' => 'setOrderId',
            'way_code' => 'setWayCode',
            'client_ip' => 'setClientIp',
            'channel_extra' => 'setChannelExtra',
        ];
        foreach ($setters as $field => $setter) {
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

    private function quickPaySmsCheckFromArray(array $params)
    {
        $request = new QuickPaySmsCheckRequest();
        $setters = [
            'way_code' => 'setWayCode',
            'client_ip' => 'setClientIp',
            'pay_order_id' => 'setPayOrderId',
            'mch_order_no' => 'setMchOrderNo',
            'verify_code' => 'setVerifyCode',
        ];
        foreach ($setters as $field => $setter) {
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

    private function electronicAccountUserOpenFromArray(array $params)
    {
        $request = new ElectronicAccountUserOpenRequest();
        $setters = [
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
        ];
        foreach ($setters as $field => $setter) {
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

    private function electronicAccountBusinessOpenFromArray(array $params)
    {
        $request = new ElectronicAccountBusinessOpenRequest();
        $setters = [
            'order_id' => 'setOrderId',
            'account_no' => 'setAccountNo',
            'client_ip' => 'setClientIp',
            'channel_extra' => 'setChannelExtra',
        ];
        foreach ($setters as $field => $setter) {
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

    private function electronicAccountBusinessChangeFromArray(array $params)
    {
        $request = new ElectronicAccountBusinessChangeRequest();
        $setters = [
            'order_id' => 'setOrderId',
            'account_no' => 'setAccountNo',
            'client_ip' => 'setClientIp',
            'channel_extra' => 'setChannelExtra',
        ];
        foreach ($setters as $field => $setter) {
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

    private function electronicAccountUserQueryFromArray(array $params)
    {
        $request = new ElectronicAccountUserQueryRequest();
        if (array_key_exists('account_no', $params)) {
            $request->setAccountNo($params['account_no']);
            unset($params['account_no']);
        }
        if (array_key_exists('client_ip', $params)) {
            $request->setClientIp($params['client_ip']);
            unset($params['client_ip']);
        }
        if ($params) {
            $request->setExtendInfo($params);
        }
        return $request;
    }

    private function electronicAccountSettlementQueryFromArray(array $params)
    {
        $request = new ElectronicAccountSettlementQueryRequest();
        $setters = [
            'order_id' => 'setOrderId',
            'way_code' => 'setWayCode',
            'account_no' => 'setAccountNo',
            'begin_date' => 'setBeginDate',
            'end_date' => 'setEndDate',
            'page_size' => 'setPageSize',
            'page_num' => 'setPageNum',
        ];
        foreach ($setters as $field => $setter) {
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

    private function electronicAccountBalanceWithdrawFromArray(array $params)
    {
        $request = new ElectronicAccountBalanceWithdrawRequest();
        $setters = [
            'order_id' => 'setOrderId',
            'way_code' => 'setWayCode',
            'account_no' => 'setAccountNo',
            'withdraw_amount' => 'setWithdrawAmount',
        ];
        foreach ($setters as $field => $setter) {
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

    private function electronicAccountSingleWithdrawQueryFromArray(array $params)
    {
        $request = new ElectronicAccountSingleWithdrawQueryRequest();
        $setters = [
            'way_code' => 'setWayCode',
            'account_no' => 'setAccountNo',
            'withdraw_date' => 'setWithdrawDate',
            'order_id' => 'setOrderId',
        ];
        foreach ($setters as $field => $setter) {
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

    private function electronicAccountBatchWithdrawQueryFromArray(array $params)
    {
        $request = new ElectronicAccountBatchWithdrawQueryRequest();
        $setters = [
            'way_code' => 'setWayCode',
            'account_no' => 'setAccountNo',
            'begin_date' => 'setBeginDate',
            'end_date' => 'setEndDate',
            'page_size' => 'setPageSize',
            'page_num' => 'setPageNum',
        ];
        foreach ($setters as $field => $setter) {
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

    private function queryRefundFromArray(array $params)
    {
        $request = new QueryRefundRequest();
        if (array_key_exists('mch_refund_no', $params)) {
            $request->setMchRefundNo($params['mch_refund_no']);
            unset($params['mch_refund_no']);
        }
        if (array_key_exists('order_refund_id', $params)) {
            $request->setOrderRefundId($params['order_refund_id']);
            unset($params['order_refund_id']);
        }
        if ($params) {
            $request->setExtendInfo($params);
        }
        return $request;
    }

    private function refundFromArray(array $params)
    {
        $request = new RefundRequest();
        $setters = [
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
        ];
        foreach ($setters as $field => $setter) {
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

    private function queryOrderFromArray(array $params)
    {
        $request = new QueryOrderRequest();
        if (array_key_exists('mch_order_no', $params)) {
            $request->setMchOrderNo($params['mch_order_no']);
            unset($params['mch_order_no']);
        }
        if (array_key_exists('pay_order_id', $params)) {
            $request->setPayOrderId($params['pay_order_id']);
            unset($params['pay_order_id']);
        }
        if ($params) {
            $request->setExtendInfo($params);
        }
        return $request;
    }

    /** 驼峰属性转为下划线参数。 */
    public function objectToArray(BaseRequest $request)
    {
        $class      = new ReflectionClass($request);
        $properties = $class->getProperties();
        $arrayData  = [];
        $extends    = [];

        foreach ($properties as $prop) {
            $attrName = $prop->getName();
            $getter   = 'get' . ucfirst($attrName);
            if (!$class->hasMethod($getter)) continue;
            $val = $class->getMethod($getter)->invoke($request);
            if (!isset($val)) continue;

            if ($attrName === 'extendInfos') {
                $extends = $val;
            } else {
                $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $attrName));
                $arrayData[$snake] = $val;
            }
        }

        $funcCode = $class->hasMethod('getFunctionCode')
            ? $class->getMethod('getFunctionCode')->invoke($request)
            : '';

        $duplicates = array_intersect_key($extends, $arrayData);
        if ($duplicates) {
            throw new LsPayValidationException(
                '扩展参数不允许覆盖请求字段: ' . implode(', ', array_keys($duplicates))
            );
        }

        return [
            'params'   => array_merge($arrayData, $extends),
            'funcCode' => $funcCode,
        ];
    }
}
