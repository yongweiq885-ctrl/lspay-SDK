<?php
/**
 * 统一下单分账接收方。
 *
 * 对应 channel_extra.div_list 中的单个对象。
 */
namespace LsPaySdk\request;

use LsPaySdk\exceptions\LsPayValidationException;

final class DivisionReceiver
{
    /** @var string|null 分账接收方用户 ID */
    private $transIn;

    /** @var int|null 分账金额，单位：分；不传时使用平台配置比例 */
    private $divAmt;

    /** @var string|null 分账说明 */
    private $description;

    public function __construct($transIn = null)
    {
        if ($transIn !== null) {
            $this->setTransIn($transIn);
        }
    }

    public function setTransIn($transIn)
    {
        if (!is_string($transIn) && !is_numeric($transIn)) {
            throw new LsPayValidationException('div_list.trans_in 必须是字符串');
        }
        $this->transIn = trim((string)$transIn);
        return $this;
    }

    public function setDivAmt($divAmt)
    {
        if ($divAmt === null || $divAmt === '') {
            $this->divAmt = null;
            return $this;
        }
        if (filter_var($divAmt, FILTER_VALIDATE_INT) === false || (int)$divAmt <= 0) {
            throw new LsPayValidationException('div_list.div_amt 必须是大于 0 的整数（单位：分）');
        }
        $this->divAmt = (int)$divAmt;
        return $this;
    }

    public function setDescription($description)
    {
        if ($description === null || $description === '') {
            $this->description = null;
            return $this;
        }
        if (!is_string($description)) {
            throw new LsPayValidationException('div_list.description 必须是字符串');
        }
        $this->description = $description;
        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        if ($this->transIn === null || $this->transIn === '') {
            throw new LsPayValidationException('div_list.trans_in 必填');
        }

        $value = ['trans_in' => $this->transIn];
        if ($this->divAmt !== null) {
            $value['div_amt'] = $this->divAmt;
        }
        if ($this->description !== null) {
            $value['description'] = $this->description;
        }
        return $value;
    }
}
