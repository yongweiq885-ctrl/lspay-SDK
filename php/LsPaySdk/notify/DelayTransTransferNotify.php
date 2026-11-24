<?php
/**
 * 延时分账订单转账异步通知。
 *
 * 所有收到且非空的字段（sign 除外）均由 BaseNotify 参与验签；
 * 未知新增字段会保留在 getRaw() 中。
 */
namespace LsPaySdk\notify;

use LsPaySdk\config\MerConfig;
use LsPaySdk\enums\DelayTransTaskStatusEnum;
use LsPaySdk\exceptions\LsPayValidationException;

final class DelayTransTransferNotify extends BaseNotify
{
    public function __construct(array $data, MerConfig $config)
    {
        parent::__construct($data, $config);

        $this->requireString('task_id', 64);
        $this->requireString('mch_no', 30);
        $this->requireString('app_id', 24);
        $this->requireString('order_no', 30);
        $targetAmount = $this->requirePositiveInteger('target_amount');
        $processedAmount = $this->requireNonNegativeInteger('processed_amount');
        $remainingAmount = $this->requireNonNegativeInteger('remaining_amount');

        $status = $this->requireString('status', 16);
        if (!DelayTransTaskStatusEnum::isValid($status)) {
            throw new LsPayValidationException('通知字段 status 非法');
        }
        if ($processedAmount > $targetAmount || $remainingAmount > $targetAmount) {
            throw new LsPayValidationException('通知金额不能超过 target_amount');
        }

        $progress = $this->requireString('progress', 8);
        if (!is_numeric($progress) || (float)$progress < 0 || (float)$progress > 100) {
            throw new LsPayValidationException(
                '通知字段 progress 必须是 0.0-100.0 的数字'
            );
        }

        $this->optionalString('completed_time', 32);
        $this->requireString('account_no', 64);
        $this->requireString('account_name', 128);
        $this->optionalString('remark', 255);

        if (in_array(
            $status,
            [DelayTransTaskStatusEnum::PENDING, DelayTransTaskStatusEnum::FAILED],
            true
        ) && ($processedAmount !== 0 || $remainingAmount !== $targetAmount)) {
            throw new LsPayValidationException(
                'PENDING/FAILED 状态的通知金额关系非法'
            );
        }
        if (in_array(
            $status,
            [DelayTransTaskStatusEnum::PROCESSING, DelayTransTaskStatusEnum::PARTIAL],
            true
        ) && ($processedAmount <= 0
            || $processedAmount >= $targetAmount
            || $remainingAmount !== $targetAmount - $processedAmount)) {
            throw new LsPayValidationException(
                'PROCESSING/PARTIAL 状态的通知金额关系非法'
            );
        }
    }

    public function getTaskId() { return $this->value('task_id'); }
    public function getOrderNo() { return $this->value('order_no'); }
    public function getTargetAmount() { return $this->integerValue('target_amount'); }
    public function getProcessedAmount() { return $this->integerValue('processed_amount'); }
    public function getRemainingAmount() { return $this->integerValue('remaining_amount'); }
    public function getTaskStatus() { return $this->value('status'); }
    public function getProgress() { return (float)$this->value('progress'); }
    public function getCompletedTime() { return $this->value('completed_time'); }
    public function getAccountNo() { return $this->value('account_no'); }
    public function getAccountName() { return $this->value('account_name'); }
    public function getRemark() { return $this->value('remark'); }

    public function isSucceeded()
    {
        return $this->getTaskStatus() === DelayTransTaskStatusEnum::COMPLETED;
    }

    public function isPartial()
    {
        return $this->getTaskStatus() === DelayTransTaskStatusEnum::PARTIAL;
    }

    public function assertTask($orderNo, $targetAmount)
    {
        $this->assertExpectedString('order_no', $orderNo);
        $this->assertExpectedInteger('target_amount', $targetAmount);
        return $this;
    }

    public function getIdempotencyKey()
    {
        return implode(':', [
            'delaytrans-transfer',
            $this->getTaskId(),
            $this->getTaskStatus(),
            (string)$this->getProcessedAmount(),
        ]);
    }

    private function requireNonNegativeInteger($field)
    {
        $value = $this->requireString($field, 20);
        if (!preg_match('/^\d+$/', $value)) {
            throw new LsPayValidationException(
                "通知字段 $field 必须是非负整数"
            );
        }
        return (int)$value;
    }
}
