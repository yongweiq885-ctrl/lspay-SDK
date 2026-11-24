<?php
/**
 * 支付订单异步通知。
 */
namespace LsPaySdk\notify;

use LsPaySdk\config\MerConfig;
use LsPaySdk\enums\OrderStateEnum;
use LsPaySdk\exceptions\LsPayValidationException;

final class PaymentNotify extends BaseNotify
{
    public function __construct(array $data, MerConfig $config)
    {
        parent::__construct($data, $config);

        $this->requireString('pay_order_id', 30);
        $this->requireString('mch_no', 30);
        $this->requireString('app_id', 24);
        $this->requireString('mch_order_no', 30);
        $this->requireString('way_code', 30);
        $this->requirePositiveInteger('amount');
        $this->assertCurrency();
        $this->requireState(OrderStateEnum::all());
        $this->requireString('subject', 64);
        $this->requireString('body', 256);
        $this->optionalString('channel_order_no', 128);
        $this->optionalString('err_code', 128);
        $this->optionalString('err_msg', 512);
        $this->optionalString('ext_param', 512);
        $this->requireDigits('add_time', 10, 10);
        if ($this->value('success_time') !== null
            && $this->value('success_time') !== '') {
            $this->requireDigits('success_time', 10, 10);
        }
        $this->requireDigits('req_time', 10, 13);
        $this->assertEventPrefix('payment');

        $clientIp = $this->optionalString('client_ip', 32);
        if ($clientIp !== null
            && filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new LsPayValidationException(
                '通知字段 client_ip 必须是合法 IPv4 地址'
            );
        }
    }

    public function getMchOrderNo() { return $this->value('mch_order_no'); }
    public function getAmount() { return $this->integerValue('amount'); }
    public function getClientIp() { return $this->value('client_ip'); }
    public function getSubject() { return $this->value('subject'); }
    public function getBody() { return $this->value('body'); }
    public function getChannelOrderNo() { return $this->value('channel_order_no'); }
    public function getErrCode() { return $this->value('err_code'); }
    public function getErrMsg() { return $this->value('err_msg'); }

    /** state=2 且事件类型匹配才表示支付成功。 */
    public function isSucceeded()
    {
        $eventType = $this->getEventType();
        return $this->getState() === OrderStateEnum::PAID
            && ($eventType === null || $eventType === 'payment.succeeded');
    }

    public function assertOrder($mchOrderNo, $amount, $currency = 'cny')
    {
        $this->assertExpectedString('mch_order_no', $mchOrderNo);
        $this->assertExpectedInteger('amount', $amount);
        $this->assertExpectedString('currency', strtolower((string)$currency));
        return $this;
    }

    public function getIdempotencyKey()
    {
        return implode(':', [
            'payment',
            $this->getPayOrderId(),
            $this->getEventType() ?: (string)$this->getState(),
        ]);
    }
}
