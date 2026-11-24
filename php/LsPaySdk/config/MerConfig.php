<?php
/**
 * 商户配置对象
 */
namespace LsPaySdk\config;

class MerConfig
{
    /** 商户号 */
    public $mch_no = '';

    /** 应用 ID */
    public $app_id = '';

    /** MD5 签名密钥 */
    public $md5_key = '';

    /** 默认异步通知地址（可空，下单时可覆盖） */
    public $notify_url = '';

    /** 默认同步跳转地址（可空，下单时可覆盖） */
    public $return_url = '';
}
