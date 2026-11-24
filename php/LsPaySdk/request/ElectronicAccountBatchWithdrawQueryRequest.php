<?php
/**
 * 电子账户批量提现查询请求。
 *
 * 端点：/gateway/user/balance/withdraw/query
 */
namespace LsPaySdk\request;

use DateTime;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

class ElectronicAccountBatchWithdrawQueryRequest extends BaseRequest
{
    protected $wayCode = WayCodeEnum::WITHDRAW_QUERY;
    protected $accountNo;
    protected $beginDate;
    protected $endDate;
    protected $pageSize;
    protected $pageNum;

    public function getFunctionCode()
    {
        return EndpointEnum::ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY;
    }

    public function getResponseClass()
    {
        return \LsPaySdk\response\ElectronicAccountBatchWithdrawQueryResponse::class;
    }

    public function getWayCode() { return $this->wayCode; }
    public function getAccountNo() { return $this->accountNo; }
    public function getBeginDate() { return $this->beginDate; }
    public function getEndDate() { return $this->endDate; }
    public function getPageSize() { return $this->pageSize; }
    public function getPageNum() { return $this->pageNum; }

    public function setWayCode($v) { $this->wayCode = trim((string)$v); return $this; }
    public function setAccountNo($v) { $this->accountNo = trim((string)$v); return $this; }
    public function setBeginDate($v) { $this->beginDate = trim((string)$v); return $this; }
    public function setEndDate($v) { $this->endDate = trim((string)$v); return $this; }
    public function setPageSize($v) { $this->pageSize = $v; return $this; }
    public function setPageNum($v) { $this->pageNum = $v; return $this; }

    public function validate()
    {
        if ($this->wayCode !== WayCodeEnum::WITHDRAW_QUERY) {
            throw new LsPayValidationException(
                'way_code 固定为 ' . WayCodeEnum::WITHDRAW_QUERY
            );
        }
        $this->assertRequiredString('account_no', $this->accountNo, 32);

        $begin = $this->parseDate('begin_date', $this->beginDate);
        $end = $this->parseDate('end_date', $this->endDate);
        if ($begin > $end) {
            throw new LsPayValidationException('begin_date 不能晚于 end_date');
        }

        // 文档写“跨度小于 3 天”，但官方示例跨度为 8 天，因此不在客户端强制。
        $this->assertOptionalPositiveInteger('page_size', $this->pageSize);
        $this->assertOptionalPositiveInteger('page_num', $this->pageNum);
    }

    private function parseDate($field, $value)
    {
        if (!is_string($value) && !is_numeric($value)) {
            throw new LsPayValidationException("$field 必须是 yyyyMMdd 格式日期");
        }
        $value = (string)$value;
        $date = DateTime::createFromFormat('!Ymd', $value);
        $errors = DateTime::getLastErrors();
        if (strlen($value) !== 8 || !$date
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Ymd') !== $value) {
            throw new LsPayValidationException("$field 必须是合法的 yyyyMMdd 格式日期");
        }
        return $date;
    }

    private function assertRequiredString($field, $value, $maxLength)
    {
        if ($value === null || trim((string)$value) === '') {
            throw new LsPayValidationException("$field 必填");
        }
        $length = function_exists('mb_strlen')
            ? mb_strlen((string)$value, 'UTF-8')
            : strlen((string)$value);
        if ($length > $maxLength) {
            throw new LsPayValidationException("$field 长度不能超过 $maxLength");
        }
    }

    private function assertOptionalPositiveInteger($field, $value)
    {
        if ($value === null || $value === '') {
            return;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1) {
            throw new LsPayValidationException("$field 必须是正整数");
        }
    }
}
