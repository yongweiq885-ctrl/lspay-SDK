<?php
/**
 * 电子账户用户查询响应。
 */
namespace LsPaySdk\response;

use LsPaySdk\enums\ElectronicAccountChannelStatusEnum;

class ElectronicAccountUserQueryResponse extends ElectronicAccountResponse
{
    public function getAccountNo() { return $this->accountValue('account_no'); }
    public function getAccountName() { return $this->accountValue('account_name'); }
    public function getAccountType() { return $this->accountValue('account_type'); }
    public function getRelationType() { return $this->accountValue('relation_type'); }
    public function getRelationTypeName() { return $this->accountValue('relation_type_name'); }
    public function getDivisionProfit() { return $this->accountValue('division_profit'); }
    public function getChannelStatus() { return $this->accountValue('channel_status'); }
    public function getRespDesc() { return $this->accountValue('resp_desc'); }

    /** 返回平台原始 channel_extra（JSON 字符串或对象）。 */
    public function getChannelExtra()
    {
        return $this->rawValue('channel_extra');
    }

    /** 尝试将 channel_extra 解析为关联数组。 */
    public function getChannelExtraArray()
    {
        $value = $this->getChannelExtra();
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) && json_last_error() === JSON_ERROR_NONE
            ? $decoded
            : [];
    }

    /** 查询资料中的 channel_status 是否为 1。开户成功看开户接口的 trans_status。 */
    public function isOnboarded()
    {
        return $this->isSuccessful()
            && $this->getChannelStatus()
                === ElectronicAccountChannelStatusEnum::SUCCEEDED;
    }

    private function rawValue($key)
    {
        $data = $this->getData();
        if (is_array($data) && array_key_exists($key, $data)) {
            return $data[$key];
        }
        $raw = $this->getRaw();
        return array_key_exists($key, $raw) ? $raw[$key] : null;
    }
}
