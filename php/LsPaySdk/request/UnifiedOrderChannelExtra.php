<?php
/** 统一下单 channel_extra 构造器。 */
namespace LsPaySdk\request;

use LsPaySdk\enums\PayDataTypeEnum;
use LsPaySdk\exceptions\LsPayValidationException;

final class UnifiedOrderChannelExtra extends DivisionChannelExtra
{
    public function setBuyerId($buyerId)
    {
        return $this->set('buyer_id', $buyerId);
    }

    public function setOpenid($openid)
    {
        return $this->set('openid', $openid);
    }

    public function setAuthCode($authCode)
    {
        return $this->set('auth_code', $authCode);
    }

    public function setChannelUser($channelUser)
    {
        return $this->set('channel_user', $channelUser);
    }

    public function setChannelCardId($channelCardId)
    {
        return $this->set('channel_card_id', $channelCardId);
    }

    public function setPayDataType($payDataType)
    {
        if (!PayDataTypeEnum::isValid($payDataType)) {
            throw new LsPayValidationException(
                'channel_extra.pay_data_type 非法: ' . (string)$payDataType
            );
        }
        return $this->set('pay_data_type', $payDataType);
    }
}
