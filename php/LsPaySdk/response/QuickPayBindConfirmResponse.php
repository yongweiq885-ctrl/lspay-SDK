<?php
/**
 * 快捷支付绑卡确认响应。
 */
namespace LsPaySdk\response;

class QuickPayBindConfirmResponse extends QuickPayResponse
{
    public function getChannelUser()
    {
        return $this->quickPayValue('channel_user');
    }

    public function getChannelCardId()
    {
        return $this->quickPayValue('channel_card_id');
    }

    public function getRespDesc()
    {
        return $this->quickPayValue('resp_desc');
    }

    /** 绑卡成功且同时取得渠道用户 ID 与绑卡 ID。 */
    public function hasBinding()
    {
        return $this->isSuccessful()
            && $this->getChannelUser() !== null
            && $this->getChannelUser() !== ''
            && $this->getChannelCardId() !== null
            && $this->getChannelCardId() !== '';
    }
}
