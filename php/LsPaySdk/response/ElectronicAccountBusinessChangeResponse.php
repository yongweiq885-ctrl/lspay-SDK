<?php
/**
 * 电子账户业务变更响应。
 */
namespace LsPaySdk\response;

class ElectronicAccountBusinessChangeResponse extends ElectronicAccountResponse
{
    public function getAccountNo()
    {
        return $this->accountValue('account_no');
    }

    public function getRespDesc()
    {
        return $this->accountValue('resp_desc');
    }
}
