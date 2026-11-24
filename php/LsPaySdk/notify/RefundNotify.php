<?php
/**
 * 退款订单异步通知。
 */
namespace LsPaySdk\notify;

use LsPaySdk\config\MerConfig;
use LsPaySdk\enums\RefundStateEnum;
use LsPaySdk\exceptions\LsPayValidationException;

final class RefundNotify extends BaseNotify
{
    public function __construct(array $data, MerConfig $config)
    {
        parent::__construct($data, $config);

        if ($this->requireString('notify_type', 64) !== 'refund') {
            throw new LsPayValidationException(
                '通知字段 notify_type 必须是 refund'
            );
        }
        $this->requireString('event_type', 64);
        $this->assertEventPrefix('refund');
        $this->requireString('pay_order_id', 30);
        $this->requireString('mch_no', 30);
        $this->requireString('app_id', 24);
        $this->requireString('mch_refund_no', 30);
        $this->requireString('way_code', 30);
        $payAmount = $this->requirePositiveInteger('pay_amount');
        $refundAmount = $this->requirePositiveInteger('refund_amount');
        if ($refundAmount > $payAmount) {
            throw new LsPayValidationException(
                '通知字段 refund_amount 不能大于 pay_amount'
            );
        }
        $this->assertCurrency();
        $this->requireState(RefundStateEnum::all());
        $this->requireString('ext_param', 512, true);
        $this->assertDateTime('add_time');
        $this->assertDateTime('success_time');
        $this->requireDigits('req_time', 10, 10);
    }

    public function getMchRefundNo() { return $this->value('mch_refund_no'); }
    public function getPayAmount() { return $this->integerValue('pay_amount'); }
    public function getRefundAmount() { return $this->integerValue('refund_amount'); }

    public function isSucceeded()
    {
        return $this->getState() === RefundStateEnum::SUCCEEDED
            && $this->getEventType() === 'refund.succeeded';
    }

    public function assertRefund(
        $mchRefundNo,
        $refundAmount,
        $currency = 'cny',
        $payAmount = null
    ) {
        $this->assertExpectedString('mch_refund_no', $mchRefundNo);
        $this->assertExpectedInteger('refund_amount', $refundAmount);
        $this->assertExpectedString('currency', strtolower((string)$currency));
        if ($payAmount !== null) {
            $this->assertExpectedInteger('pay_amount', $payAmount);
        }
        return $this;
    }

    public function getIdempotencyKey()
    {
        return implode(':', [
            'refund',
            $this->getMchRefundNo(),
            $this->getEventType(),
        ]);
    }

    private function assertDateTime($field)
    {
        $value = $this->requireString($field, 19);
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value
        );
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d H:i:s') !== $value) {
            throw new LsPayValidationException(
                "通知字段 $field 必须是 yyyy-MM-dd HH:mm:ss 格式"
            );
        }
    }
}
