<?php
/** 分账 channel_extra 构造器。 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

class DivisionChannelExtra
{
    /** @var array<string,mixed> */
    protected $values = [];

    /**
     * @param array<int,DivisionReceiver|array<string,mixed>> $receivers
     * @return $this
     */
    public function setDivisionReceivers(array $receivers)
    {
        $values = [];
        foreach ($receivers as $receiver) {
            if ($receiver instanceof DivisionReceiver) {
                $values[] = $receiver->toArray();
                continue;
            }
            if (!is_array($receiver)) {
                throw new LsPayValidationException(
                    'channel_extra.div_list 每一项必须是 DivisionReceiver 或关联数组'
                );
            }
            $values[] = $receiver;
        }
        return $this->set('div_list', $values);
    }

    /** @return $this */
    public function addDivisionReceiver(DivisionReceiver $receiver)
    {
        if (!isset($this->values['div_list']) || !is_array($this->values['div_list'])) {
            $this->values['div_list'] = [];
        }
        $this->values['div_list'][] = $receiver->toArray();
        return $this;
    }

    /**
     * 写入平台后续新增的渠道参数。
     *
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function set($key, $value)
    {
        $key = trim((string)$key);
        if ($key === '') {
            throw new LsPayValidationException('channel_extra 参数名不能为空');
        }
        if ($value === null || $value === '') {
            unset($this->values[$key]);
        } else {
            $this->values[$key] = $value;
        }
        return $this;
    }

    /** @return array<string,mixed> */
    public function toArray()
    {
        return $this->values;
    }

    public function toJson()
    {
        $json = json_encode(
            (object)$this->values,
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
