<?php
/** 商户配置、日志和 POST 请求。 */
namespace LsPaySdk\core;

use LsPaySdk\config\MerConfig;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\PayDataTypeEnum;
use LsPaySdk\exceptions\LsPayBizException;
use LsPaySdk\exceptions\LsPayConfigException;
use LsPaySdk\exceptions\LsPayNetworkException;
use LsPaySdk\exceptions\LsPayProtocolException;
use LsPaySdk\exceptions\LsPayValidationException;

class LsPay
{
    const BASE_API_URL_PROD = 'https://api.lspay.tech';
    const BASE_API_URL_TEST = 'https://sandbox.lspay.tech';

    public static $isDebug;
    public static $isProdMode;
    public static $logDir = '';

    /** 秒 */
    protected $connectTimeout = 10;
    /** 秒 */
    protected $requestTimeout = 30;
    protected $userAgent = 'LsPaySdk/' . SDK_VERSION;
    protected $proxy;

    /** 多商户配置存储：merchantKey => MerConfig */
    private static $merConfigMap = [];

    /**
     * 初始化 / 注册商户配置
     *
     * @param string|array $config_info 配置文件路径或配置数组
     * @param bool         $is_object   true=$config_info 是数组；false=文件路径
     * @param string       $merchantKey 多商户场景下的键名，默认 default
     */
    public static function init($config_info, $is_object = false, $merchantKey = 'default')
    {
        if (!$is_object && empty($config_info)) {
            throw new LsPayConfigException('缺少 SDK 配置信息');
        }

        if ($is_object) {
            if (!is_array($config_info)) {
                throw new LsPayConfigException('SDK 配置信息必须是数组');
            }
            $config_obj = $config_info;
        } else {
            if (!file_exists($config_info)) {
                throw new LsPayConfigException('SDK 配置文件不存在: ' . $config_info);
            }
            $cfg_file_str = file_get_contents($config_info);
            $config_obj   = json_decode($cfg_file_str, true);
            if (!$config_obj) {
                throw new LsPayConfigException('SDK 配置文件格式异常: ' . $config_info);
            }
        }

        // 环境标志
        self::$isDebug    = defined('DEBUG') && DEBUG;
        self::$logDir     = defined('LOG') ? LOG : dirname(__FILE__) . '/log';
        self::$isProdMode = defined('PROD_MODE') ? PROD_MODE : true;

        // 环境变量覆盖（优先级最高）
        foreach ([
            'mch_no'     => 'LSPAY_MCH_NO',
            'app_id'     => 'LSPAY_APP_ID',
            'md5_key'    => 'LSPAY_MD5_KEY',
            'notify_url' => 'LSPAY_NOTIFY_URL',
            'return_url' => 'LSPAY_RETURN_URL',
        ] as $key => $env) {
            $v = getenv($env);
            if ($v !== false && $v !== '') $config_obj[$key] = $v;
        }

        $cfg = new MerConfig();
        $cfg->mch_no     = isset($config_obj['mch_no'])     ? trim((string)$config_obj['mch_no'])     : '';
        $cfg->app_id     = isset($config_obj['app_id'])     ? trim((string)$config_obj['app_id'])     : '';
        $cfg->md5_key    = isset($config_obj['md5_key'])    ? (string)$config_obj['md5_key']           : '';
        $cfg->notify_url = isset($config_obj['notify_url']) ? trim((string)$config_obj['notify_url']) : '';
        $cfg->return_url = isset($config_obj['return_url']) ? trim((string)$config_obj['return_url']) : '';

        self::addMerConfig($merchantKey, $cfg);
    }

    public static function addMerConfig($merchantKey, MerConfig $merConfig)
    {
        if ($merConfig->mch_no === '' || $merConfig->app_id === '' || $merConfig->md5_key === '') {
            throw new LsPayConfigException('配置缺少 mch_no / app_id / md5_key');
        }
        if (strlen($merConfig->mch_no) > 30) {
            throw new LsPayConfigException('mch_no 长度不能超过 30');
        }
        if (strlen($merConfig->app_id) > 24) {
            throw new LsPayConfigException('app_id 长度不能超过 24');
        }
        foreach (['notify_url', 'return_url'] as $field) {
            $url = $merConfig->$field;
            if ($url !== '' && (
                strlen($url) > 128
                || filter_var($url, FILTER_VALIDATE_URL) === false
                || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            )) {
                throw new LsPayConfigException("$field 必须是长度不超过 128 的 HTTP(S) URL");
            }
        }
        self::$merConfigMap[$merchantKey] = $merConfig;
    }

    public static function getConfig($merchantKey = 'default')
    {
        return isset(self::$merConfigMap[$merchantKey]) ? self::$merConfigMap[$merchantKey] : null;
    }

    public static function listMerchants()
    {
        return array_keys(self::$merConfigMap);
    }

    public function setConnectTimeout($seconds)
    {
        $this->connectTimeout = $this->positiveTimeout(
            'connect timeout',
            $seconds
        );
        return $this;
    }

    public function setRequestTimeout($seconds)
    {
        $this->requestTimeout = $this->positiveTimeout(
            'request timeout',
            $seconds
        );
        return $this;
    }

    public function setUserAgent($userAgent)
    {
        $userAgent = trim((string)$userAgent);
        if ($userAgent === '') {
            throw new LsPayValidationException('user agent 不能为空');
        }
        $this->userAgent = $userAgent;
        return $this;
    }

    public function setProxy($proxy)
    {
        $this->proxy = $proxy === null || $proxy === ''
            ? null
            : trim((string)$proxy);
        return $this;
    }

    public static function writeLog($message, $level = 'INFO')
    {
        if (!self::$isDebug) return;
        if (!is_dir(self::$logDir)) @mkdir(self::$logDir, 0777, true);
        $logFile = self::$logDir . '/lspay_' . date('Ymd') . '.log';
        $addr    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        $line    = sprintf("[%s] [%s] %s %s\n", $level, gmdate('Y-m-d\TH:i:s\Z'), $addr, $message);
        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    public function getRequestUrl($funcCode)
    {
        $base = self::$isProdMode ? self::BASE_API_URL_PROD : self::BASE_API_URL_TEST;
        if (!LsPayTools::endWith($base, '/')) $base .= '/';
        return $base . ltrim($funcCode, '/');
    }

    /**
     * 通用 POST 请求
     *
     * @param string $funcCode    端点功能编码（含相对路径），如 gateway/query
     * @param array  $params      业务参数（不含 mch_no/app_id/req_time/version/sign_type/sign）
     * @param string $merchantKey 商户 key
     * @param string $responseClass 响应对象类名
     * @return LsPayResponse
     */
    public function post(
        $funcCode,
        array $params,
        $merchantKey = 'default',
        $responseClass = LsPayResponse::class
    )
    {
        if ($funcCode === EndpointEnum::UNIFIED_ORDER && self::isCashierOrder($params)) {
            throw new LsPayValidationException(
                'pay_data_type=cashier 时网关返回收银台页面，不能由服务端调用接口；'
                . '请使用 LsPayClient::buildCashierForm() 生成表单，由用户浏览器提交'
            );
        }
        list($url, $data) = $this->buildSignedRequest($funcCode, $params, $merchantKey);

        $respText = $this->curlRequest($url, $data);
        return $this->decodeResponse($url, $respText, $responseClass);
    }

    /**
     * 注入公共字段并签名，返回 [请求地址, 已签名参数]。
     *
     * @return array{0:string,1:array}
     */
    protected function buildSignedRequest($funcCode, array $params, $merchantKey = 'default')
    {
        $merConfig = self::getConfig($merchantKey);
        if (!$merConfig) {
            throw new LsPayConfigException("商户配置不存在: $merchantKey");
        }

        $reserved = ['mch_no', 'app_id', 'req_time', 'version', 'sign_type', 'sign'];
        foreach ($reserved as $field) {
            if (array_key_exists($field, $params)) {
                throw new LsPayValidationException(
                    "$field 是 SDK 管理的公共参数，业务参数不允许覆盖"
                );
            }
        }
        $params = $this->filterEmpty($params);

        // 统一下单未显式传通知地址时，使用商户配置中的默认值。
        if ($funcCode === EndpointEnum::UNIFIED_ORDER) {
            if (!isset($params['notify_url']) && $merConfig->notify_url !== '') {
                $params['notify_url'] = $merConfig->notify_url;
            }
            if (!isset($params['return_url']) && $merConfig->return_url !== '') {
                $params['return_url'] = $merConfig->return_url;
            }
        }

        // 注入公共字段
        $data = array_merge([
            'mch_no'    => $merConfig->mch_no,
            'app_id'    => $merConfig->app_id,
            'req_time'  => LsPayTools::msecTime(),
            'version'   => API_VERSION,
            'sign_type' => 'MD5',
        ], $params);

        $data['sign'] = LsPayTools::sign($data, $merConfig->md5_key);
        $data = $this->prepareRequestData($data, $merConfig);

        $url = $this->getRequestUrl($funcCode);
        self::writeLog(
            "REQUEST $url " . json_encode(
                $this->sanitizeForLog($data),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
        return [$url, $data];
    }

    /** 统一下单 channel_extra.pay_data_type 是否为 cashier。 */
    public static function isCashierOrder(array $params)
    {
        if (!isset($params['channel_extra'])) {
            return false;
        }
        $extra = is_array($params['channel_extra'])
            ? $params['channel_extra']
            : json_decode((string)$params['channel_extra'], true);
        return is_array($extra)
            && isset($extra['pay_data_type'])
            && $extra['pay_data_type'] === PayDataTypeEnum::CASHIER;
    }

    private function decodeResponse($url, $respText, $responseClass)
    {
        $decoded = json_decode($respText, true);
        if (!is_array($decoded)) {
            self::writeLog(
                'RESPONSE_PROTOCOL_ERROR 非 JSON 对象响应已省略，长度='
                . strlen($respText),
                'ERROR'
            );
            throw new LsPayProtocolException(
                '立收通响应不是合法 JSON 对象，长度=' . strlen($respText)
            );
        }
        $logResponse = $this->sanitizeForLog($decoded);
        self::writeLog(
            "RESPONSE $url " . json_encode(
                $logResponse,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
        if ($responseClass !== LsPayResponse::class
            && !is_subclass_of($responseClass, LsPayResponse::class)) {
            throw new LsPayConfigException("非法响应类: $responseClass");
        }
        return new $responseClass($decoded);
    }

    /** 调试客户端可在此改写请求并重新签名。 */
    protected function prepareRequestData(array $data, MerConfig $merConfig)
    {
        return $data;
    }

    protected function filterEmpty(array $params)
    {
        return array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        });
    }

    private function positiveTimeout($field, $seconds)
    {
        if (filter_var($seconds, FILTER_VALIDATE_INT) === false
            || (int)$seconds <= 0) {
            throw new LsPayValidationException("$field 必须是大于 0 的整数秒");
        }
        return (int)$seconds;
    }

    /** 日志脱敏：签名及渠道用户凭据不得写入明文日志。 */
    protected function sanitizeForLog(array $data)
    {
        $sensitive = [
            'sign',
            'md5_key',
            'auth_code',
            'openid',
            'buyer_id',
            'trans_in',
            'trans_out',
            'account_no',
            'account_name',
            'account_alias',
            'mch_user_id',
            'card_id',
            'card_name',
            'card_no',
            'cert_id',
            'cert_begin_date',
            'cert_end_date',
            'card_mp',
            'vip_code',
            'expiration',
            'verify_code',
            'channel_user',
            'channel_card_id',
            'name',
            'cert_no',
            'mobile_no',
            'email',
            'reg_name',
            'short_name',
            'license_code',
            'reg_detail',
            'legal_name',
            'legal_cert_no',
            'contact_name',
            'contact_mobile',
            'mp',
            'pay_info',
        ];
        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $data[$key] = $this->maskValue($value);
                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->sanitizeForLog($value);
            }
        }

        if (isset($data['channel_extra']) && is_string($data['channel_extra'])) {
            $extra = json_decode($data['channel_extra'], true);
            if (is_array($extra)) {
                $data['channel_extra'] = json_encode(
                    $this->sanitizeForLog($extra),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }
        }
        return $data;
    }

    private function maskValue($value)
    {
        $value = (string)$value;
        $length = strlen($value);
        if ($length <= 4) return '****';
        return substr($value, 0, 2) . '****' . substr($value, -2);
    }

    protected function curlRequest($url, array $data)
    {
        if (!function_exists('curl_init')) {
            throw new LsPayNetworkException('PHP cURL 扩展未安装或未启用');
        }
        $ch = curl_init();
        if ($ch === false) {
            throw new LsPayNetworkException('无法初始化 PHP cURL');
        }
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->requestTimeout,
            CURLOPT_NOSIGNAL       => true,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'],
        ]);
        if ($this->proxy !== null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err  = curl_error($ch);
            $errn = curl_errno($ch);
            curl_close($ch);
            throw new LsPayNetworkException(
                "请求立收通失败({$errn}): $err",
                0,
                $errn
            );
        }
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpStatus < 200 || $httpStatus >= 300) {
            $errorBody = json_decode($resp, true);
            $errorDetail = is_array($errorBody)
                ? json_encode(
                    $this->sanitizeForLog($errorBody),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
                : '[响应正文已省略]';
            throw new LsPayNetworkException(
                "立收通 HTTP 状态异常: $httpStatus; body=$errorDetail",
                $httpStatus,
                0,
                $errorDetail
            );
        }
        return $resp;
    }
}
