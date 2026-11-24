<?php
/**
 * 电子账户用户开通响应。
 */
namespace LsPaySdk\response;

class ElectronicAccountUserOpenResponse extends ElectronicAccountResponse
{
    public function getAccountNo()
    {
        return $this->accountValue('account_no');
    }

    public function getRespDesc()
    {
        return $this->accountValue('resp_desc');
    }

    /** 开户成功且已取得平台分配的电子账户编号。 */
    public function hasAccount()
    {
        return $this->isSuccessful()
            && $this->getAccountNo() !== null
            && $this->getAccountNo() !== '';
    }
}
