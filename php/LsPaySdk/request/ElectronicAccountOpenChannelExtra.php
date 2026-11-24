<?php
/**
 * 电子账户开户 channel_extra 构造器。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class ElectronicAccountOpenChannelExtra
{
    private $values = [];

    public function setUserInfo(ElectronicAccountUserInfo $userInfo)
    {
        $this->values = ['user_info' => $userInfo->toArray()];
        return $this;
    }

    public function setMerchantInfo(ElectronicAccountMerchantInfo $merchantInfo)
    {
        $this->values = ['mer_info' => $merchantInfo->toArray()];
        return $this;
    }

    /** @return array<string,array<string,string>> */
    public function toArray()
    {
        if (!$this->values) {
            throw new LsPayValidationException(
                'channel_extra 必须设置 user_info 或 mer_info'
            );
        }
        return $this->values;
    }

    public function toJson()
    {
        $json = json_encode(
            (object)$this->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            throw new LsPayValidationException(
                'channel_extra JSON 编码失败: ' . json_last_error_msg()
            );
        }
        return $json;
    }
}
