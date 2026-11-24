<?php
/**
 * 快捷支付申请响应。
 *
 * trans_status=P 表示支付申请处理中并等待短信确认，不代表接口失败。
 */
namespace LsPaySdk\response;

use LsPaySdk\enums\QuickPayTransStatusEnum;

class QuickPayApplyResponse extends UnifiedOrderResponse
{
    /** S 或 P 都表示支付申请已被平台受理。 */
    public function isAccepted()
    {
        return !$this->isError()
            && in_array(
                strtoupper((string)$this->getTransStatus()),
                [
                    QuickPayTransStatusEnum::SUCCEEDED,
                    QuickPayTransStatusEnum::PROCESSING,
                ],
                true
            );
    }

    public function isProcessing()
    {
        return !$this->isError()
            && strtoupper((string)$this->getTransStatus())
                === QuickPayTransStatusEnum::PROCESSING;
    }
}
