<?php
/**
 * 立收通 SDK Web 调试面板
 *
 *   - 浏览器访问即可联调当前已接入的立收通接口
 *   - 同一个文件既是 UI 又是 Ajax 后端
 *   - 不侵入 LsPaySdk：靠 DebugClient 子类抓 url / body / sign
 *
 * 使用：
 *   1. 复制 config.example.json 为 config.json 并填写配置，或设置 LSPAY_* 环境变量
 *   2. （可选）复制 config_merchant_b.example.json 为 config_merchant_b.json 并填写真实配置，启用多商户切换
 *   3. 浏览器访问  http(s)://你的域名/.../php/LsPayDemo/web/index.php
 *   4. 选 profile → 选接口 → 填参数 → 点 "发起请求"
 */
namespace LsPayDemo\web;

require_once dirname(__FILE__) . '/../../LsPaySdk/init.php';
require_once dirname(__FILE__) . '/../constants/DemoConstants.php';

use LsPaySdk\core\LsPay;
use LsPaySdk\core\LsPayClient;
use LsPaySdk\core\LsPayTools;
use LsPaySdk\enums\EndpointEnum;
use LsPaySdk\enums\WayCodeEnum;
use LsPaySdk\exceptions\LsPayException;
use LsPaySdk\request\BalancePayRequest;
use LsPaySdk\request\BalanceQueryRequest;
use LsPaySdk\request\ElectronicAccountBalanceWithdrawRequest;
use LsPaySdk\request\ElectronicAccountBatchWithdrawQueryRequest;
use LsPaySdk\request\ElectronicAccountBusinessChangeRequest;
use LsPaySdk\request\ElectronicAccountBusinessOpenRequest;
use LsPaySdk\request\ElectronicAccountSettlementQueryRequest;
use LsPaySdk\request\ElectronicAccountSingleWithdrawQueryRequest;
use LsPaySdk\request\ElectronicAccountUserQueryRequest;
use LsPaySdk\request\ElectronicAccountUserOpenRequest;
use LsPaySdk\request\DelayTransCreateTaskRequest;
use LsPaySdk\request\DelayTransConfirmRefundRequest;
use LsPaySdk\request\DelayTransConfirmRequest;
use LsPaySdk\request\DelayTransUnconfirmQueryRequest;
use LsPaySdk\request\QueryOrderRequest;
use LsPaySdk\request\QueryRefundRequest;
use LsPaySdk\request\QuickPayApplyRequest;
use LsPaySdk\request\QuickPayBindApplyRequest;
use LsPaySdk\request\QuickPayBindConfirmRequest;
use LsPaySdk\request\QuickPayConfirmRequest;
use LsPaySdk\request\QuickPaySmsCheckRequest;
use LsPaySdk\request\QuickPayUnbindRequest;
use LsPaySdk\request\RefundRequest;
use LsPaySdk\request\UnifiedOrderRequest;

// ----------------------- 调试面板访问保护 -----------------------
function debug_env_enabled($name)
{
    $value = strtolower(trim((string)getenv($name)));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

$remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$isLoopback = in_array($remoteAddr, ['127.0.0.1', '::1', ''], true);
$allowRemote = debug_env_enabled('LSPAY_DEBUG_PANEL_ALLOW_REMOTE');
$panelToken = trim((string)getenv('LSPAY_DEBUG_PANEL_TOKEN'));
$providedToken = isset($_SERVER['HTTP_X_LSPAY_DEBUG_TOKEN'])
    ? (string)$_SERVER['HTTP_X_LSPAY_DEBUG_TOKEN']
    : (isset($_GET['token']) ? (string)$_GET['token'] : '');

if ((!$isLoopback && !$allowRemote)
    || ($panelToken !== '' && !hash_equals($panelToken, $providedToken))) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Forbidden: SDK 调试面板仅允许授权访问');
}

function debug_config_enabled($configFile, $key)
{
    if (!file_exists($configFile)) {
        return false;
    }
    $config = json_decode(file_get_contents($configFile), true);
    if (!is_array($config) || !isset($config[$key])) {
        return false;
    }
    return $config[$key] === true
        || in_array(strtolower(trim((string)$config[$key])), ['1', 'true', 'yes', 'on'], true);
}

$ALLOW_LIVE_REQUESTS = debug_env_enabled('LSPAY_DEBUG_PANEL_ALLOW_LIVE')
    || debug_config_enabled(dirname(__FILE__) . '/../config/config.json', 'debug_panel_allow_live');

// ----------------------- 环境与多商户加载 -----------------------
if (!defined('DEBUG'))     define('DEBUG',     true);
if (!defined('PROD_MODE')) define('PROD_MODE', true);

$DEFAULT_CONFIG = dirname(__FILE__) . '/../config/config.json';
$MERCH_B_CONFIG = dirname(__FILE__) . '/../config/config_merchant_b.json';
$CONFIG_ERRORS  = [];

function unconfigured_fields($configFile)
{
    $config = file_exists($configFile)
        ? (json_decode(file_get_contents($configFile), true) ?: [])
        : [];
    $fields = [];
    foreach ([
        'mch_no'  => 'LSPAY_MCH_NO',
        'app_id'  => 'LSPAY_APP_ID',
        'md5_key' => 'LSPAY_MD5_KEY',
    ] as $field => $env) {
        $value = getenv($env);
        if ($value === false || $value === '') {
            $value = isset($config[$field]) ? trim((string)$config[$field]) : '';
        }
        if ($value === '' || preg_match('/^(YOUR_|MERCH_[A-Z0-9]+_)/', $value)) {
            $fields[] = $field;
        }
    }
    return $fields;
}

function profile_config_error($profile, array $configErrors)
{
    if (isset($configErrors[$profile])) {
        return $configErrors[$profile];
    }
    if (!LsPay::getConfig($profile)) {
        return "商户 profile [$profile] 未注册，请检查 LsPayDemo/config 配置";
    }
    return '';
}

$configSources = [
    'default' => [$DEFAULT_CONFIG, 'config.json'],
    'merch_b' => [$MERCH_B_CONFIG, 'config_merchant_b.json'],
];
foreach ($configSources as $profileKey => $source) {
    list($configFile, $configName) = $source;
    if ($profileKey !== 'default' && !file_exists($configFile)) {
        continue;
    }
    $missing = unconfigured_fields($configFile);
    if ($missing) {
        $CONFIG_ERRORS[$profileKey] = "未配置商户信息，请先在 $configName 中配置 "
            . implode('、', $missing);
        continue;
    }
    try {
        if (file_exists($configFile)) {
            LsPay::init($configFile, false, $profileKey);
        } else {
            LsPay::init([], true, $profileKey);
        }
    } catch (LsPayException $e) {
        $CONFIG_ERRORS[$profileKey] = "$configName 配置有误：" . $e->getMessage();
    }
}

/**
 * 抓取 url/data/sign 的调试客户端
 *   - 覆盖父类 protected curlRequest，把真实请求拦下来记录
 *   - dryRun 模式不发起 HTTP，直接返回构造好的响应
 */
class DebugClient extends LsPayClient
{
    public $lastUrl  = '';
    public $lastData = [];
    public $lastSign = '';
    public $dryRun   = false;
    public $commonOverrides = [];

    protected function prepareRequestData(
        array $data,
        \LsPaySdk\config\MerConfig $merConfig
    )
    {
        if ($this->commonOverrides) {
            $customSign = isset($this->commonOverrides['sign'])
                ? trim((string)$this->commonOverrides['sign'])
                : '';
            unset($data['sign']);
            foreach (['mch_no', 'app_id', 'req_time', 'version', 'sign_type'] as $field) {
                if (array_key_exists($field, $this->commonOverrides)
                    && $this->commonOverrides[$field] !== '') {
                    $data[$field] = $this->commonOverrides[$field];
                }
            }
            if ($customSign !== '') {
                $data['sign'] = $customSign;
            } else {
                $data['sign'] = LsPayTools::sign($data, $merConfig->md5_key);
            }
        }
        return $data;
    }

    protected function curlRequest($url, array $data)
    {
        $this->lastUrl  = $url;
        $this->lastData = $data;
        $this->lastSign = isset($data['sign']) ? $data['sign'] : '';
        if ($this->dryRun) {
            if (substr($url, -strlen(EndpointEnum::BALANCE_QUERY))
                === EndpointEnum::BALANCE_QUERY) {
                return json_encode([
                    'acct_type' => '01',
                    'acct_stat' => 'N',
                    'balance_amt' => '1',
                    'avl_balance' => '1',
                    'frz_bal' => '1',
                    'dm_amt' => '0.00',
                ]);
            }
            if (isset($data['way_code'])
                && $data['way_code'] === WayCodeEnum::BALANCE_PAY) {
                return json_encode([
                    'status' => 200,
                    'msg' => '成功',
                    'data' => [
                        'err_msg' => '交易成功',
                        'mch_order_no' => isset($data['mch_order_no'])
                            ? $data['mch_order_no']
                            : '8504920400284465',
                        'pay_order_id' => '8709543910489194496',
                        'amount' => isset($data['amount']) ? $data['amount'] : 300,
                        'order_state' => 2,
                        'trans_status' => 'S',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY))
                === EndpointEnum::ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'success',
                    'data' => [
                        'count' => 1,
                        'page_size' => 15,
                        'page_num' => 1,
                        'data' => [[
                            'bank_name' => '',
                            'batch_trans_type' => 'ENCHASHMENT',
                            'card_name' => '陈大海',
                            'card_no' => '621467*********1530',
                            'cash_amt' => '11105.44',
                            'fee_amt' => '5.56',
                            'fee_flag' => '2',
                            'org_req_date' => '20250422',
                            'remark' => '',
                            'trans_desc' => '交易失败,出账失败,账户可用余额不足',
                            'trans_status' => 'failed',
                            'settle_date' => '20250422',
                            'order_id' => '12',
                        ]],
                        'status' => 'succeess',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY))
                === EndpointEnum::ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'success',
                    'data' => [
                        'cash_amt' => '1.00',
                        'fee_amt' => '0.00',
                        'withdraw_date' => '20260609',
                        'order_id' => '1111111111',
                        'trans_desc' => '成功',
                        'trans_status' => 'succeeded',
                        'status' => 'succeess',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW))
                === EndpointEnum::ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'success',
                    'data' => [
                        'err_msg' => '受理成功',
                        'status' => 'success',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY))
                === EndpointEnum::ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'trans_id' => 'DRY_RUN_SETTLEMENT_ID',
                        'trans_date' => isset($data['begin_date'])
                            ? $data['begin_date']
                            : date('Ymd'),
                        'settle_cycle' => 'T1',
                        'card_no' => '622262****8994',
                        'card_name' => 'DRY_RUN_ACCOUNT_NAME',
                        'bank_code' => '01040000',
                        'trans_amt' => '1.00',
                        'fee_amt' => '0.10',
                        'trans_stat' => 'S',
                        'settle_type' => '1',
                        'settle_abstract' => 'DRY_RUN',
                        'settle_batch_no' => '1',
                        'settle_desc' => '',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::ELECTRONIC_ACCOUNT_USER_QUERY))
                === EndpointEnum::ELECTRONIC_ACCOUNT_USER_QUERY) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'trans_status' => 'S',
                        'account_no' => isset($data['account_no'])
                            ? $data['account_no']
                            : 'DRY_RUN_ACCOUNT_NO',
                        'account_name' => 'DRY_RUN_ACCOUNT_NAME',
                        'channel_extra' => '{}',
                        'channel_status' => '1',
                        'resp_desc' => '查询成功',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_CHANGE))
                === EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_CHANGE) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'trans_status' => 'S',
                        'resp_desc' => '请求成功，业务处理中！',
                        'account_no' => isset($data['account_no'])
                            ? $data['account_no']
                            : 'DRY_RUN_ACCOUNT_NO',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_OPEN))
                === EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_OPEN) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'trans_status' => 'S',
                        'account_no' => isset($data['account_no'])
                            ? $data['account_no']
                            : 'DRY_RUN_ACCOUNT_NO',
                        'resp_desc' => '业务开通成功',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::ELECTRONIC_ACCOUNT_USER_OPEN))
                === EndpointEnum::ELECTRONIC_ACCOUNT_USER_OPEN) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'trans_status' => 'S',
                        'account_no' => 'DRY_RUN_ACCOUNT_NO',
                        'resp_desc' => '开户成功',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::QUICK_PAY_SMS_CHECK))
                === EndpointEnum::QUICK_PAY_SMS_CHECK) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'trans_status' => 'S',
                        'err_msg' => '短信校验成功',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::QUICK_PAY_UNBIND))
                === EndpointEnum::QUICK_PAY_UNBIND) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'trans_status' => 'S',
                        'order_id' => isset($data['order_id'])
                            ? $data['order_id']
                            : 'DRY_RUN_UNBIND_ORDER',
                        'resp_desc' => '解绑成功',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::QUICK_PAY_CONFIRM))
                === EndpointEnum::QUICK_PAY_CONFIRM) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'trans_status' => 'S',
                        'err_msg' => '支付确认成功',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::UNIFIED_ORDER))
                === EndpointEnum::UNIFIED_ORDER
                && isset($data['way_code'])
                && $data['way_code'] === WayCodeEnum::BANK_FAST) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'err_msg' => '申请处理中',
                        'mch_order_no' => $data['mch_order_no'],
                        'pay_order_id' => 'DRY_RUN_QUICK_PAY_ORDER',
                        'amount' => (int)$data['amount'],
                        'order_state' => 1,
                        'trans_status' => 'P',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::QUICK_PAY_BIND_CONFIRM))
                === EndpointEnum::QUICK_PAY_BIND_CONFIRM) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'trans_status' => 'S',
                        'channel_user' => 'DRY_RUN_CHANNEL_USER',
                        'channel_card_id' => 'DRY_RUN_CHANNEL_CARD',
                        'resp_desc' => '绑卡成功',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::QUICK_PAY_BIND_APPLY))
                === EndpointEnum::QUICK_PAY_BIND_APPLY) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'trans_status' => 'S',
                        'trans_id' => 'DRY_RUN_BIND_TRANS',
                        'order_id' => isset($data['order_id'])
                            ? $data['order_id']
                            : 'DRY_RUN_BIND_ORDER',
                        'resp_desc' => '绑卡申请已受理',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::DELAY_TRANS_CREATE_TASK))
                === EndpointEnum::DELAY_TRANS_CREATE_TASK) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'task_id' => 'DRY_RUN_TASK',
                        'order_no' => isset($data['order_no'])
                            ? $data['order_no']
                            : 'DRY_RUN_ORDER',
                        'account_no' => isset($data['account_no'])
                            ? $data['account_no']
                            : 'DRY_RUN_ACCOUNT',
                        'account_name' => isset($data['account_name'])
                            ? $data['account_name']
                            : 'DRY_RUN_NAME',
                        'amount' => isset($data['amount']) ? (int)$data['amount'] : 100,
                        'status' => 'PENDING',
                        'selected_orders' => 1,
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::DELAY_TRANS_UNCONFIRM_QUERY))
                === EndpointEnum::DELAY_TRANS_UNCONFIRM_QUERY) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'unconfirm_amount' => 100,
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::DELAY_TRANS_CONFIRM_REFUND))
                === EndpointEnum::DELAY_TRANS_CONFIRM_REFUND) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'unconfirm_amount' => 100,
                        'confirmed_amount' => 0,
                        'confirm_order_id' => isset($data['confirm_order_id'])
                            ? $data['confirm_order_id']
                            : 'DRY_RUN_CONFIRM_ORDER',
                        'confirm_refund_no' => isset($data['confirm_refund_no'])
                            ? $data['confirm_refund_no']
                            : 'DRY_RUN_CONFIRM_REFUND',
                        'trans_status' => 'S',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::DELAY_TRANS_CONFIRM))
                === EndpointEnum::DELAY_TRANS_CONFIRM) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'unconfirm_amount' => 0,
                        'confirmed_amount' => 100,
                        'confirm_order_id' => 'DRY_RUN_CONFIRM_ORDER',
                        'batch_order_id' => 'DRY_RUN_BATCH_ORDER',
                        'trans_status' => 'S',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::QUERY_ORDER)) === EndpointEnum::QUERY_ORDER) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'pay_order_id' => isset($data['pay_order_id']) ? $data['pay_order_id'] : 'DRY_RUN_PAY_ORDER',
                        'mch_order_no' => isset($data['mch_order_no']) ? $data['mch_order_no'] : 'DRY_RUN_MCH_ORDER',
                        'amount' => 100,
                        'currency' => 'cny',
                        'state' => 0,
                        'way_code' => 'ALI_QR',
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::QUERY_REFUND)) === EndpointEnum::QUERY_REFUND) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'order_refund_id' => isset($data['order_refund_id'])
                            ? $data['order_refund_id']
                            : 'DRY_RUN_REFUND_ORDER',
                        'mch_refund_no' => isset($data['mch_refund_no'])
                            ? $data['mch_refund_no']
                            : 'DRY_RUN_MCH_REFUND',
                        'pay_order_id' => 'DRY_RUN_PAY_ORDER',
                        'pay_amount' => 100,
                        'refund_amount' => 1,
                        'currency' => 'cny',
                        'state' => 0,
                    ],
                ]);
            }
            if (substr($url, -strlen(EndpointEnum::REFUND)) === EndpointEnum::REFUND) {
                return json_encode([
                    'status' => 200,
                    'msg' => 'DRY_RUN（未发起真实 HTTP）',
                    'data' => [
                        'refund_order_id' => 'DRY_RUN_REFUND_ORDER',
                        'mch_refund_no' => $data['mch_refund_no'],
                        'refund_amount' => $data['refund_amount'],
                        'pay_amount' => $data['refund_amount'],
                        'state' => 0,
                    ],
                    'sign' => 'DRY_RUN_SIGN',
                ]);
            }
            return json_encode([
                'status' => 200,
                'msg'  => 'DRY_RUN（未发起真实 HTTP）',
                'data' => [
                    'dry_run' => true,
                    'order_state' => 0,
                    'trans_status' => 'S',
                ],
            ]);
        }
        return parent::curlRequest($url, $data);
    }
}

function mark_dry_run_text($value)
{
    if (!is_array($value)) {
        return $value;
    }
    foreach ($value as $key => $item) {
        if (in_array($key, ['msg', 'err_msg', 'resp_desc', 'trans_desc'], true) && is_string($item)) {
            $value[$key] = 'DRY_RUN 模拟数据';
        } else {
            $value[$key] = mark_dry_run_text($item);
        }
    }
    return $value;
}

// ============================================================
//                         AJAX 后端
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower($_SERVER['CONTENT_TYPE']) : '';
    if (strpos($contentType, 'application/json') !== 0) {
        http_response_code(415);
        echo json_encode(['ok' => false, 'error_info' => '仅接受 application/json 请求']);
        exit;
    }
    if (!empty($_SERVER['HTTP_ORIGIN']) && !empty($_SERVER['HTTP_HOST'])) {
        $originHost = (string)parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
        $originPort = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_PORT);
        $originAuthority = strtolower(
            $originHost . ($originPort ? ':' . $originPort : '')
        );
        if (!hash_equals(strtolower((string)$_SERVER['HTTP_HOST']), $originAuthority)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error_info' => '拒绝跨站调试请求']);
            exit;
        }
    }
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $action  = isset($body['action'])  ? $body['action']  : '';
    $profile = isset($body['profile']) ? $body['profile'] : 'default';
    $dryRun  = !empty($body['dry_run']);
    $params  = isset($body['params'])  ? $body['params']  : [];
    if (!$dryRun && !$ALLOW_LIVE_REQUESTS) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error_info' => '真实请求未开启，请在 config.json 中设置 "debug_panel_allow_live": true',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!$dryRun
        && (!isset($body['confirm_live'])
            || $body['confirm_live'] !== 'SEND_LIVE_REQUEST')) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error_info' => '真实请求缺少二次确认',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $commonOverrides = [];
    foreach (['mch_no', 'app_id', 'req_time', 'version', 'sign_type', 'sign'] as $field) {
        if (is_array($params) && array_key_exists($field, $params)) {
            $commonOverrides[$field] = $params[$field];
            unset($params[$field]);
        }
    }

    $resp_payload = [
        'ok'           => false,
        'dry_run'      => $dryRun,
        'request_url'  => '',
        'request_data' => null,
        'sign'         => '',
        'response_raw' => '',
        'response_json'=> null,
        'is_error'     => true,
        'error_info'   => '',
        'data'         => null,
    ];

    try {
        $profileError = profile_config_error($profile, $CONFIG_ERRORS);
        if ($profileError !== '') {
            throw new LsPayException($profileError);
        }

        $req = build_request($action, $params);

        $client = new DebugClient($profile);
        $client->dryRun = $dryRun;
        $client->commonOverrides = $commonOverrides;

        if ($req instanceof UnifiedOrderRequest
            && LsPay::isCashierOrder(['channel_extra' => $req->getChannelExtra()])) {
            $form = $client->buildCashierForm($req);
            $resp_payload['request_url']  = $form['action'];
            $resp_payload['request_data'] = $form['fields'];
            $resp_payload['sign']         = isset($form['fields']['sign']) ? $form['fields']['sign'] : '';
            $resp_payload['cashier_form'] = $form;
            $resp_payload['is_error']     = false;
            $resp_payload['ok']           = true;
        } else {
            $resp = $client->postRequest($req);

            $raw_arr = $resp->getRaw();
            $data_arr = $resp->getData();
            if ($dryRun) {
                $raw_arr = mark_dry_run_text($raw_arr);
                $data_arr = mark_dry_run_text($data_arr);
            }
            $err_arr = $resp->getErrorInfo();
            $err_str = '';
            if ($resp->isError()) {
                $code = isset($err_arr['status']) ? $err_arr['status'] : '';
                $msg  = isset($err_arr['msg'])  ? $err_arr['msg']  : '';
                $err_str = '[' . $code . '] ' . $msg;
            }
            $resp_payload['request_url']   = $client->lastUrl;
            $resp_payload['request_data']  = $client->lastData;
            $resp_payload['sign']          = $client->lastSign;
            $resp_payload['response_raw']  = json_encode($raw_arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $resp_payload['response_json'] = $raw_arr;
            $resp_payload['is_error']      = $resp->isError();
            $resp_payload['error_info']    = $err_str;
            $resp_payload['data']          = $data_arr;
            $resp_payload['ok']            = !$resp->isError();
        }
    } catch (LsPayException $e) {
        if (isset($client)) {
            $resp_payload['request_url']  = $client->lastUrl;
            $resp_payload['request_data'] = $client->lastData;
            $resp_payload['sign']         = $client->lastSign;
        }
        $resp_payload['error_info'] = '[' . basename(str_replace('\\', '/', get_class($e))) . '] ' . $e->getMessage();
    } catch (\Throwable $e) {
        $resp_payload['error_info'] = '[' . get_class($e) . '] ' . $e->getMessage();
    }

    echo json_encode($resp_payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ----------------------- 构造 Request -----------------------
function build_request($action, array $p)
{
    switch ($action) {
        case 'balancepay':
            $r = new BalancePayRequest();
            set_if($p, 'mch_order_no',  function ($v) use ($r) { $r->setMchOrderNo($v); });
            set_if($p, 'way_code',      function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'amount',        function ($v) use ($r) { $r->setAmount((int)$v); });
            set_if($p, 'currency',      function ($v) use ($r) { $r->setCurrency($v); });
            set_if($p, 'subject',       function ($v) use ($r) { $r->setSubject($v); });
            set_if($p, 'body',          function ($v) use ($r) { $r->setBody($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            set_if($p, 'client_ip',     function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'notify_url',    function ($v) use ($r) { $r->setNotifyUrl($v); });
            set_if($p, 'return_url',    function ($v) use ($r) { $r->setReturnUrl($v); });
            set_if($p, 'expired_time',  function ($v) use ($r) { $r->setExpiredTime($v); });
            set_if($p, 'division_mode', function ($v) use ($r) { $r->setDivisionMode($v); });
            set_if($p, 'ext_param',     function ($v) use ($r) { $r->setExtParam($v); });
            return $r;
        case 'balancequery':
            $r = new BalanceQueryRequest();
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            set_if($p, 'way_code', function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'client_ip', function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'account_no', function ($v) use ($r) { $r->setAccountNo($v); });
            return $r;
        case 'unifiedorder':
        case 'divisionorder':
        case 'quickpayapply':
            $r = $action === 'quickpayapply'
                ? new QuickPayApplyRequest()
                : new UnifiedOrderRequest();
            set_if($p, 'mch_order_no',  function ($v) use ($r) { $r->setMchOrderNo($v); });
            set_if($p, 'way_code',      function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'amount',        function ($v) use ($r) { $r->setAmount((int)$v); });
            set_if($p, 'currency',      function ($v) use ($r) { $r->setCurrency($v); });
            set_if($p, 'subject',       function ($v) use ($r) { $r->setSubject($v); });
            set_if($p, 'body',          function ($v) use ($r) { $r->setBody($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            set_if($p, 'client_ip',     function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'notify_url',    function ($v) use ($r) { $r->setNotifyUrl($v); });
            set_if($p, 'return_url',    function ($v) use ($r) { $r->setReturnUrl($v); });
            set_if($p, 'expired_time',  function ($v) use ($r) { $r->setExpiredTime($v); });
            set_if($p, 'division_mode', function ($v) use ($r) { $r->setDivisionMode($v); });
            set_if($p, 'polling_mode',  function ($v) use ($r) { $r->setPollingMode($v); });
            set_if($p, 'ext_param',     function ($v) use ($r) { $r->setExtParam($v); });
            return $r;
        case 'queryorder':
            $r = new QueryOrderRequest();
            set_if($p, 'mch_order_no', function ($v) use ($r) { $r->setMchOrderNo($v); });
            set_if($p, 'pay_order_id', function ($v) use ($r) { $r->setPayOrderId($v); });
            return $r;
        case 'refund':
            $r = new RefundRequest();
            set_if($p, 'mch_refund_no', function ($v) use ($r) { $r->setMchRefundNo($v); });
            set_if($p, 'mch_order_no',  function ($v) use ($r) { $r->setMchOrderNo($v); });
            set_if($p, 'pay_order_id',  function ($v) use ($r) { $r->setPayOrderId($v); });
            set_if($p, 'refund_amount', function ($v) use ($r) { $r->setRefundAmount($v); });
            set_if($p, 'currency',      function ($v) use ($r) { $r->setCurrency($v); });
            set_if($p, 'refund_reason', function ($v) use ($r) { $r->setRefundReason($v); });
            set_if($p, 'client_ip',     function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'notify_url',    function ($v) use ($r) { $r->setNotifyUrl($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            set_if($p, 'ext_param',     function ($v) use ($r) { $r->setExtParam($v); });
            return $r;
        case 'queryrefund':
            $r = new QueryRefundRequest();
            set_if($p, 'mch_refund_no',   function ($v) use ($r) { $r->setMchRefundNo($v); });
            set_if($p, 'order_refund_id', function ($v) use ($r) { $r->setOrderRefundId($v); });
            return $r;
        case 'delaytransconfirm':
            $r = new DelayTransConfirmRequest();
            set_if($p, 'pay_order_id', function ($v) use ($r) { $r->setPayOrderId($v); });
            set_if($p, 'mch_order_no', function ($v) use ($r) { $r->setMchOrderNo($v); });
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            return $r;
        case 'delaytransconfirmrefund':
            $r = new DelayTransConfirmRefundRequest();
            set_if($p, 'confirm_order_id', function ($v) use ($r) { $r->setConfirmOrderId($v); });
            set_if($p, 'confirm_refund_no', function ($v) use ($r) { $r->setConfirmRefundNo($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            return $r;
        case 'delaytransunconfirmquery':
            return new DelayTransUnconfirmQueryRequest();
        case 'delaytranscreatetask':
            $r = new DelayTransCreateTaskRequest();
            set_if($p, 'account_no', function ($v) use ($r) { $r->setAccountNo($v); });
            set_if($p, 'account_name', function ($v) use ($r) { $r->setAccountName($v); });
            set_if($p, 'order_no', function ($v) use ($r) { $r->setOrderNo($v); });
            set_if($p, 'amount', function ($v) use ($r) { $r->setAmount($v); });
            set_if($p, 'notify_url', function ($v) use ($r) { $r->setNotifyUrl($v); });
            set_if($p, 'remark', function ($v) use ($r) { $r->setRemark($v); });
            return $r;
        case 'quickpaybindapply':
            $r = new QuickPayBindApplyRequest();
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            set_if($p, 'client_ip', function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            return $r;
        case 'quickpaybindconfirm':
            $r = new QuickPayBindConfirmRequest();
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            set_if($p, 'client_ip', function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            set_if($p, 'verify_code', function ($v) use ($r) { $r->setVerifyCode($v); });
            return $r;
        case 'quickpayconfirm':
            $r = new QuickPayConfirmRequest();
            set_if($p, 'way_code', function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'client_ip', function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'pay_order_id', function ($v) use ($r) { $r->setPayOrderId($v); });
            set_if($p, 'mch_order_no', function ($v) use ($r) { $r->setMchOrderNo($v); });
            set_if($p, 'verify_code', function ($v) use ($r) { $r->setVerifyCode($v); });
            return $r;
        case 'quickpayunbind':
            $r = new QuickPayUnbindRequest();
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            set_if($p, 'way_code', function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'client_ip', function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            return $r;
        case 'quickpaysmscheck':
            $r = new QuickPaySmsCheckRequest();
            set_if($p, 'way_code', function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'client_ip', function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'pay_order_id', function ($v) use ($r) { $r->setPayOrderId($v); });
            set_if($p, 'mch_order_no', function ($v) use ($r) { $r->setMchOrderNo($v); });
            set_if($p, 'verify_code', function ($v) use ($r) { $r->setVerifyCode($v); });
            return $r;
        case 'electronicaccountuseropen':
            $r = new ElectronicAccountUserOpenRequest();
            set_if($p, 'way_code', function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            set_if($p, 'account_name', function ($v) use ($r) { $r->setAccountName($v); });
            set_if($p, 'account_type', function ($v) use ($r) { $r->setAccountType($v); });
            set_if($p, 'relation_type', function ($v) use ($r) { $r->setRelationType($v); });
            set_if($p, 'relation_type_name', function ($v) use ($r) { $r->setRelationTypeName($v); });
            set_if($p, 'account_alias', function ($v) use ($r) { $r->setAccountAlias($v); });
            set_if($p, 'division_profit', function ($v) use ($r) { $r->setDivisionProfit($v); });
            set_if($p, 'client_ip', function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            return $r;
        case 'electronicaccountbusinessopen':
            $r = new ElectronicAccountBusinessOpenRequest();
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            set_if($p, 'account_no', function ($v) use ($r) { $r->setAccountNo($v); });
            set_if($p, 'client_ip', function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            return $r;
        case 'electronicaccountbusinesschange':
            $r = new ElectronicAccountBusinessChangeRequest();
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            set_if($p, 'account_no', function ($v) use ($r) { $r->setAccountNo($v); });
            set_if($p, 'client_ip', function ($v) use ($r) { $r->setClientIp($v); });
            set_if($p, 'channel_extra', function ($v) use ($r) { $r->setChannelExtra($v); });
            return $r;
        case 'electronicaccountuserquery':
            $r = new ElectronicAccountUserQueryRequest();
            set_if($p, 'account_no', function ($v) use ($r) { $r->setAccountNo($v); });
            set_if($p, 'client_ip', function ($v) use ($r) { $r->setClientIp($v); });
            return $r;
        case 'electronicaccountsettlementquery':
            $r = new ElectronicAccountSettlementQueryRequest();
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            set_if($p, 'way_code', function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'account_no', function ($v) use ($r) { $r->setAccountNo($v); });
            set_if($p, 'begin_date', function ($v) use ($r) { $r->setBeginDate($v); });
            set_if($p, 'end_date', function ($v) use ($r) { $r->setEndDate($v); });
            set_if($p, 'page_size', function ($v) use ($r) { $r->setPageSize($v); });
            set_if($p, 'page_num', function ($v) use ($r) { $r->setPageNum($v); });
            return $r;
        case 'electronicaccountbalancewithdraw':
            $r = new ElectronicAccountBalanceWithdrawRequest();
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            set_if($p, 'way_code', function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'account_no', function ($v) use ($r) { $r->setAccountNo($v); });
            set_if($p, 'withdraw_amount', function ($v) use ($r) { $r->setWithdrawAmount($v); });
            return $r;
        case 'electronicaccountsinglewithdrawquery':
            $r = new ElectronicAccountSingleWithdrawQueryRequest();
            set_if($p, 'way_code', function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'account_no', function ($v) use ($r) { $r->setAccountNo($v); });
            set_if($p, 'withdraw_date', function ($v) use ($r) { $r->setWithdrawDate($v); });
            set_if($p, 'order_id', function ($v) use ($r) { $r->setOrderId($v); });
            return $r;
        case 'electronicaccountbatchwithdrawquery':
            $r = new ElectronicAccountBatchWithdrawQueryRequest();
            set_if($p, 'way_code', function ($v) use ($r) { $r->setWayCode($v); });
            set_if($p, 'account_no', function ($v) use ($r) { $r->setAccountNo($v); });
            set_if($p, 'begin_date', function ($v) use ($r) { $r->setBeginDate($v); });
            set_if($p, 'end_date', function ($v) use ($r) { $r->setEndDate($v); });
            set_if($p, 'page_size', function ($v) use ($r) { $r->setPageSize($v); });
            set_if($p, 'page_num', function ($v) use ($r) { $r->setPageNum($v); });
            return $r;
        default:
            throw new \InvalidArgumentException("未知 action: $action");
    }
}
function set_if(array $p, $key, $cb)
{
    if (isset($p[$key]) && $p[$key] !== '' && $p[$key] !== null) $cb($p[$key]);
}

// ----------------------- 准备给前端的元信息 -----------------------
$WAY_CODES = WayCodeEnum::paymentMethods();
$ENDPOINTS = [
    'unifiedorder' => EndpointEnum::UNIFIED_ORDER,
    'divisionorder'=> EndpointEnum::UNIFIED_ORDER,
    'queryorder'   => EndpointEnum::QUERY_ORDER,
    'refund'       => EndpointEnum::REFUND,
    'queryrefund'  => EndpointEnum::QUERY_REFUND,
    'delaytransconfirm' => EndpointEnum::DELAY_TRANS_CONFIRM,
    'delaytransconfirmrefund' => EndpointEnum::DELAY_TRANS_CONFIRM_REFUND,
    'delaytransunconfirmquery' => EndpointEnum::DELAY_TRANS_UNCONFIRM_QUERY,
    'delaytranscreatetask' => EndpointEnum::DELAY_TRANS_CREATE_TASK,
    'quickpaybindapply' => EndpointEnum::QUICK_PAY_BIND_APPLY,
    'quickpaybindconfirm' => EndpointEnum::QUICK_PAY_BIND_CONFIRM,
    'quickpayapply' => EndpointEnum::UNIFIED_ORDER,
    'quickpayconfirm' => EndpointEnum::QUICK_PAY_CONFIRM,
    'quickpayunbind' => EndpointEnum::QUICK_PAY_UNBIND,
    'quickpaysmscheck' => EndpointEnum::QUICK_PAY_SMS_CHECK,
    'balancepay' => EndpointEnum::UNIFIED_ORDER,
    'balancequery' => EndpointEnum::BALANCE_QUERY,
    'electronicaccountuseropen' => EndpointEnum::ELECTRONIC_ACCOUNT_USER_OPEN,
    'electronicaccountbusinessopen' => EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_OPEN,
    'electronicaccountbusinesschange' => EndpointEnum::ELECTRONIC_ACCOUNT_BUSINESS_CHANGE,
    'electronicaccountuserquery' => EndpointEnum::ELECTRONIC_ACCOUNT_USER_QUERY,
    'electronicaccountsettlementquery' => EndpointEnum::ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY,
    'electronicaccountbalancewithdraw' => EndpointEnum::ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW,
    'electronicaccountsinglewithdrawquery' => EndpointEnum::ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY,
    'electronicaccountbatchwithdrawquery' => EndpointEnum::ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY,
];
$profiles = array_values(array_unique(array_merge(
    ['default'],
    LsPay::listMerchants(),
    array_keys($CONFIG_ERRORS)
)));
$profileCommon = [];
foreach ($profiles as $profileKey) {
    $profileConfig = LsPay::getConfig($profileKey);
    if ($profileConfig && !isset($CONFIG_ERRORS[$profileKey])) {
        $profileCommon[$profileKey] = [
            'mch_no' => $profileConfig->mch_no,
            'app_id' => $profileConfig->app_id,
        ];
    }
}
$default_notify = 'https://your.domain/notify';
$default_return = 'https://your.domain/return';
$cfg = LsPay::getConfig('default');
if ($cfg) {
    if (!empty($cfg->notify_url)) $default_notify = $cfg->notify_url;
    if (!empty($cfg->return_url)) $default_return = $cfg->return_url;
}
$gateway_base = PROD_MODE ? LsPay::BASE_API_URL_PROD : LsPay::BASE_API_URL_TEST;
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>立收通 SDK Web 调试面板</title>
<link rel="icon" href="favicon.ico" sizes="16x16 32x32 48x48 64x64">
<link rel="shortcut icon" href="favicon.ico">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; height: 100%; }
body {
    font-family: -apple-system, "PingFang SC", "Microsoft YaHei", system-ui, sans-serif;
    background: linear-gradient(135deg, #f3f6fd 0%, #eef1f8 100%);
    color: #2c3e50; font-size: 14px;
}
.header {
    background: linear-gradient(90deg, #1e3a8a 0%, #0066ff 100%);
    color: #fff; padding: 18px 28px; display: flex; align-items: center;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
}
.header h1 { margin: 0; font-size: 18px; font-weight: 600; }
.header .sub { margin-left: 12px; font-size: 12px; opacity: .85; }
.header .right { margin-left: auto; display: flex; gap: 18px; align-items: center; font-size: 13px; }
.header .right label { display: flex; gap: 6px; align-items: center; cursor: pointer; }
.header select, .header input[type="checkbox"] {
    color: #2c3e50;
}
.header select { padding: 4px 8px; border-radius: 4px; border: none; }
.layout {
    display: grid; grid-template-columns: 240px 1fr 1fr; gap: 16px;
    padding: 20px; min-height: calc(100% - 60px);
}
.card {
    background: #fff; border-radius: 10px; box-shadow: 0 1px 6px rgba(28,40,80,.06);
    padding: 18px; display: flex; flex-direction: column;
}
.card h2 {
    margin: 0 0 12px 0; font-size: 14px; color: #1e3a8a;
    border-bottom: 1px solid #eef1f8; padding-bottom: 8px;
}
.nav-card {
    padding: 14px 10px; position: sticky; top: 20px; align-self: start;
    max-height: calc(100vh - 40px); overflow-y: auto; overflow-x: hidden;
}
.nav-card h2 { margin: 0 6px 8px; }
.tab-list { display: flex; flex-direction: column; }
.api-group + .api-group { border-top: 1px solid #e5e7eb; }
.api-group summary {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 6px; cursor: pointer; list-style: none; user-select: none;
    font-size: 13px; font-weight: 600; color: #111827;
}
.api-group summary::-webkit-details-marker { display: none; }
.api-group summary:hover { color: #000; }
.api-group summary::after {
    content: ""; width: 5px; height: 5px; margin-right: 4px;
    border-right: 1.5px solid #98a2b3; border-bottom: 1.5px solid #98a2b3;
    transform: rotate(-45deg);
}
.api-group[open] summary::after { transform: rotate(45deg); }
.group-tabs { display: flex; flex-direction: column; margin-bottom: 8px; }
.tab {
    padding: 7px 10px; border-radius: 4px; cursor: pointer; min-width: 0;
    display: flex; flex-direction: column; gap: 1px; color: #344054;
}
.tab + .tab { border-top: 1px solid #f0f2f5; }
.tab:hover { background: #f5f7fa; }
.tab.active { background: #eef4ff; color: #1d4ed8; }
.tab.disabled { cursor: not-allowed; opacity: .5; }
.tab.disabled:hover { background: transparent; }
.tab .name { font-size: 13px; }
.tab.active .name { font-weight: 600; }
.tab .path {
    font-family: Consolas, Menlo, monospace; font-size: 11px; color: #98a2b3;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.tab.active .path { color: #6b8fd6; }
.nav-footer {
    margin: 12px 6px 0; padding-top: 10px; border-top: 1px solid #eef1f8;
    font-size: 12px; color: #98a2b3; line-height: 1.6;
}
.nav-footer code { font-family: Consolas, Menlo, monospace; color: #667085; }

.form-row { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
.form-row label { font-size: 12px; color: #5d6b80; }
.form-row label .req { color: #e74c3c; }
.form-row input, .form-row select, .form-row textarea {
    padding: 7px 10px; border: 1px solid #dde3ee; border-radius: 5px; font-size: 13px;
    font-family: inherit; transition: border-color .15s;
}
.form-row input:focus, .form-row select:focus, .form-row textarea:focus {
    outline: none; border-color: #0066ff;
}
.form-row.has-error input,
.form-row.has-error select,
.form-row.has-error textarea { border-color: #c0392b; }
.field-error { font-size: 12px; color: #c0392b; }
.form-row .hint { font-size: 11px; color: #95a3b8; margin-top: 2px; }
.preset-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.preset-btn {
    padding: 4px 10px; border: 1px solid #dde3ee; border-radius: 5px; cursor: pointer;
    background: #f3f5f9; color: #344054; font-size: 12px; font-family: Consolas, Menlo, monospace;
}
.preset-btn:hover { background: #e8ecf3; }
.preset-btn.active { border-color: #0066ff; background: #eef4ff; color: #0066ff; }
.preset-desc { font-size: 12px; color: #e74c3c; font-style: italic; }
.preset-desc:empty { display: none; }
.form-section-title {
    margin: 14px 0 9px; color: #344054; font-size: 12px; font-weight: 700;
}
.param-guide {
    margin-bottom: 12px; border: 1px solid #e4eaf3; border-radius: 8px;
    background: #fbfcff; overflow: hidden;
}
.param-guide summary {
    padding: 9px 11px; cursor: pointer; color: #344054;
    font-size: 12px; font-weight: 700; user-select: none;
}
.param-guide-body { padding: 0 11px 10px; }
.param-guide table {
    width: 100%; border-collapse: collapse; font-size: 11px;
}
.param-guide th, .param-guide td {
    padding: 6px 5px; border-top: 1px solid #edf0f5; text-align: left;
}
.param-guide th { color: #667085; font-weight: 600; }
.param-guide td { color: #475467; }
.param-guide code { color: #175cd3; font-family: Consolas, Menlo, monospace; }
.param-guide .sign-yes { color: #047857; font-weight: 700; }
.param-guide .sign-no { color: #98a2b3; }
.form-notice {
    padding: 12px 14px; border: 1px solid #d6e4ff; border-radius: 8px;
    background: #f7f9ff; color: #475467; font-size: 12px; line-height: 1.7;
}
.config-alert {
    margin: 12px 20px 0; padding: 8px 12px; border: 1px solid #f5d9a8; border-radius: 4px;
    background: #fffaf0; color: #8a5a00; font-size: 13px; line-height: 1.8;
}
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

.actions { display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.btn {
    padding: 8px 14px; border: none; border-radius: 5px; cursor: pointer;
    font-size: 13px; font-weight: 600; transition: all .15s;
}
.btn-primary { background: #0066ff; color: #fff; }
.btn-primary:hover { background: #0055d4; }
.btn:disabled { opacity: .5; cursor: not-allowed; }
.dialog-overlay {
    position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center;
    background: rgba(15, 23, 42, .45);
}
.dialog {
    width: min(420px, calc(100vw - 32px)); padding: 20px 22px 16px; border-radius: 10px;
    background: #fff; box-shadow: 0 20px 50px rgba(15, 23, 42, .25);
}
.dialog-title { margin-bottom: 8px; color: #1f2937; font-size: 16px; font-weight: 700; }
.dialog-message { color: #475467; font-size: 14px; line-height: 1.7; word-break: break-all; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
.btn-secondary { background: #eef1f8; color: #2c3e50; }
.btn-secondary:hover { background: #dde3ee; }
.btn-warn { background: #e74c3c; color: #fff; }
.btn-warn:hover { background: #c0392b; }

.result-block { margin-bottom: 14px; }
.result-block .lab {
    font-size: 11px; color: #95a3b8; text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 4px;
}
.kv-line { display: flex; gap: 8px; font-size: 13px; align-items: center; }
.kv-line .k { color: #5d6b80; min-width: 56px; }
.kv-line .v { color: #2c3e50; font-family: Consolas, Menlo, monospace; word-break: break-all; }

pre.json {
    background: #1e293b; color: #e2e8f0; padding: 12px 14px; border-radius: 6px;
    font-family: Consolas, Menlo, monospace; font-size: 12px;
    margin: 0; overflow-x: auto; line-height: 1.55;
    max-height: 320px; overflow-y: auto;
}
pre.json .k    { color: #93c5fd; }
pre.json .s    { color: #fbcfe8; }
pre.json .n    { color: #fcd34d; }
pre.json .b    { color: #f87171; }
pre.json .null { color: #94a3b8; font-style: italic; }
pre.json .sign-hi { background: rgba(251,191,36,.25); border-radius: 2px; padding: 0 2px; }

.badge { padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
.badge-ok   { background: #d1fae5; color: #047857; }
.badge-err  { background: #fee2e2; color: #b91c1c; }
.badge-dry  { background: #fef3c7; color: #92400e; }

.empty-tip { color: #95a3b8; font-size: 12px; padding: 8px 0; text-align: center; }

@media (max-width: 1200px) {
    .layout { grid-template-columns: 200px 1fr; }
    .nav-card { grid-row: 1 / span 2; }
    .card-result { grid-column: 2; }
}
</style>
</head>
<body>

<div class="header">
    <h1>立收通 SDK Web 调试面板</h1>
    <span class="sub"><?php echo htmlspecialchars($gateway_base); ?></span>
    <div class="right">
        <label>商户 Profile
            <select id="profile">
                <?php foreach ($profiles as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <input type="checkbox" id="dryRun" checked<?php echo $ALLOW_LIVE_REQUESTS ? '' : ' disabled'; ?>>
            干跑模式（不发起真实 HTTP）
        </label>
        <?php if (!$ALLOW_LIVE_REQUESTS): ?>
            <span class="sub">真实请求未开启（config.json 中设置 "debug_panel_allow_live": true）</span>
        <?php endif; ?>
    </div>
</div>

<?php if ($CONFIG_ERRORS): ?>
<div class="config-alert">
    <?php foreach ($CONFIG_ERRORS as $profileKey => $error): ?>
        <div><?php echo count($CONFIG_ERRORS) > 1 ? htmlspecialchars($profileKey) . '：' : ''; ?><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="layout">

    <!-- 左：接口列表 -->
    <div class="card nav-card">
        <h2>接口列表</h2>
        <div class="tab-list" id="tabList">
            <?php
            $groups = [
                [
                    'name' => '支付业务',
                    'tabs' => [
                        ['unifiedorder', '统一下单', $ENDPOINTS['unifiedorder'], false],
                        ['queryorder', '订单查询', $ENDPOINTS['queryorder'], false],
                        ['refund', '申请退款', $ENDPOINTS['refund'], false],
                        ['queryrefund', '退款查询', $ENDPOINTS['queryrefund'], false],
                    ],
                ],
                [
                    'name' => '订单分账',
                    'tabs' => [
                        ['divisionorder', '订单分账示例', $ENDPOINTS['divisionorder'], false],
                        ['delaytransconfirm', '延时分账交易确认', $ENDPOINTS['delaytransconfirm'], false],
                        ['delaytransconfirmrefund', '交易确认退款', $ENDPOINTS['delaytransconfirmrefund'], false],
                        ['delaytransunconfirmquery', '可转余额查询', $ENDPOINTS['delaytransunconfirmquery'], false],
                        ['delaytranscreatetask', '延时分账订单转账', $ENDPOINTS['delaytranscreatetask'], false],
                    ],
                ],
                [
                    'name' => '快捷支付',
                    'tabs' => [
                        ['quickpaybindapply', '绑卡申请', $ENDPOINTS['quickpaybindapply'], false],
                        ['quickpaybindconfirm', '绑卡确认', $ENDPOINTS['quickpaybindconfirm'], false],
                        ['quickpayapply', '支付申请', $ENDPOINTS['quickpayapply'], false],
                        ['quickpayconfirm', '支付确认', $ENDPOINTS['quickpayconfirm'], false],
                        ['quickpayunbind', '支付解绑', $ENDPOINTS['quickpayunbind'], false],
                        ['quickpaysmscheck', '支付短信校验', $ENDPOINTS['quickpaysmscheck'], false],
                    ],
                ],
                [
                    'name' => '余额支付',
                    'tabs' => [
                        ['balancepay', '发起支付', $ENDPOINTS['balancepay'], false],
                        ['balancequery', '余额查询', $ENDPOINTS['balancequery'], false],
                    ],
                ],
                [
                    'name' => '电子账户',
                    'tabs' => [
                        ['electronicaccountuseropen', '用户开通', $ENDPOINTS['electronicaccountuseropen'], false],
                        ['electronicaccountbusinessopen', '业务开通', $ENDPOINTS['electronicaccountbusinessopen'], false],
                        ['electronicaccountbusinesschange', '业务变更', $ENDPOINTS['electronicaccountbusinesschange'], false],
                        ['electronicaccountuserquery', '用户查询', $ENDPOINTS['electronicaccountuserquery'], false],
                        ['electronicaccountsettlementquery', '结算记录查询', $ENDPOINTS['electronicaccountsettlementquery'], false],
                        ['electronicaccountbalancewithdraw', '余额提现', $ENDPOINTS['electronicaccountbalancewithdraw'], false],
                        ['electronicaccountsinglewithdrawquery', '单笔提现查询', $ENDPOINTS['electronicaccountsinglewithdrawquery'], false],
                        ['electronicaccountbatchwithdrawquery', '批量提现查询', $ENDPOINTS['electronicaccountbatchwithdrawquery'], false],
                    ],
                ],
            ];
            foreach ($groups as $groupIndex => $group):
            ?>
            <details class="api-group"<?php echo $groupIndex === 0 ? ' open' : ''; ?>>
                <summary><?php echo htmlspecialchars($group['name']); ?></summary>
                <div class="group-tabs">
                    <?php foreach ($group['tabs'] as $tabIndex => $t):
                        list($code, $name, $path, $disabled) = $t;
                    ?>
                    <div class="tab <?php
                        echo ($groupIndex === 0 && $tabIndex === 0) ? 'active ' : '';
                        echo $disabled ? 'disabled' : '';
                    ?>"<?php if ($code !== null): ?> data-action="<?php echo htmlspecialchars($code); ?>"<?php endif; ?>
                       <?php if ($disabled): ?>aria-disabled="true"<?php endif; ?>>
                        <span class="name"><?php echo htmlspecialchars($name); ?></span>
                        <span class="path" title="<?php echo htmlspecialchars($path); ?>"><?php echo htmlspecialchars($path); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
        <div class="nav-footer">商户配置：<code>LsPayDemo/config/config.json</code></div>
    </div>

    <!-- 中：表单 -->
    <div class="card card-form">
        <h2 id="formTitle">统一下单参数</h2>
        <div id="formContainer"></div>
        <div class="actions" style="margin-top:14px;">
            <button class="btn btn-secondary" id="btnFill">填充示例值</button>
            <button class="btn btn-primary" id="btnSend">发起请求</button>
            <button class="btn btn-secondary" id="btnReset">清空</button>
        </div>
    </div>

    <!-- 右：结果 -->
    <div class="card card-result">
        <h2>响应结果
            <span style="float:right;font-size:12px;font-weight:normal;color:#95a3b8;" id="statusBar"></span>
        </h2>
        <div id="resultContainer">
            <div class="empty-tip">等待发起请求…</div>
        </div>
    </div>

</div>

<script>
const WAY_CODES = <?php echo json_encode($WAY_CODES, JSON_UNESCAPED_UNICODE); ?>;
const ENDPOINTS = <?php echo json_encode($ENDPOINTS, JSON_UNESCAPED_UNICODE); ?>;
const PROFILE_COMMON = <?php echo json_encode($profileCommon, JSON_UNESCAPED_UNICODE); ?>;
const DEFAULT_NOTIFY = <?php echo json_encode($default_notify, JSON_UNESCAPED_UNICODE); ?>;
const DEFAULT_RETURN = <?php echo json_encode($default_return, JSON_UNESCAPED_UNICODE); ?>;
const ALLOW_LIVE_REQUESTS = <?php echo $ALLOW_LIVE_REQUESTS ? 'true' : 'false'; ?>;
const COMMON_REQUEST_FIELDS = [
    { key: 'mch_no', label: '商户号 mch_no', required: true, placeholder: '请先配置商户号' },
    { key: 'app_id', label: '应用 ID app_id', required: true, placeholder: '请先配置应用 ID' },
    { key: 'req_time', label: '请求时间 req_time', required: true, hint: '13 位毫秒时间戳' },
    { key: 'version', label: '接口版本 version', required: true, hint: '默认 1.0' },
    { key: 'sign_type', label: '签名类型 sign_type', required: true, hint: '默认 MD5' },
    { key: 'sign', label: '签名 sign', hint: '留空自动计算' },
];

const PAY_DATA_TYPE_PRESETS = [
    { value: 'code_img_url', desc: '返回二维码图片的URL' },
    { value: 'pay_url',      desc: '返回支付链接' },
    { value: 'cashier',      desc: '返回立收通统一收银台（form 表单跳转）' },
    { value: 'code_url',     desc: '返回二维码内容地址，需自行生成二维码' },
];

const formMeta = {
    unifiedorder: {
        title: '统一下单参数（' + ENDPOINTS.unifiedorder + '）',
        fields: [
            { key: 'mch_order_no',  label: '商户订单号 mch_order_no', required: true, type: 'text', hint: '商户系统自增/唯一' },
            { key: 'way_code',      label: '支付方式 way_code', required: true, type: 'select', options: WAY_CODES },
            { key: 'amount',        label: '金额 amount（分）', required: true, type: 'number', hint: '单位：分；1 元 = 100 分' },
            { key: 'currency',      label: '货币 currency', type: 'text', hint: '默认 cny' },
            { key: 'subject',       label: '商品标题 subject', required: true, type: 'text' },
            { key: 'body',          label: '商品描述 body', required: true, type: 'text' },
            { key: 'channel_extra', label: '渠道扩展参数 channel_extra', required: true, type: 'text', hint: 'JSON 字符串', presets: PAY_DATA_TYPE_PRESETS },
            { key: 'client_ip',     label: '客户端 IP client_ip', type: 'text' },
            { key: 'notify_url',    label: '异步通知地址 notify_url', type: 'text' },
            { key: 'return_url',    label: '同步跳转地址 return_url', type: 'text' },
            { key: 'expired_time',  label: '订单有效期 expired_time（秒）', type: 'number', hint: '正整数秒；不传时平台默认 7200 秒' },
            { key: 'division_mode', label: '分账模式 division_mode', type: 'select', options: {'0':'不分账','1':'实时分账','2':'延时分账'} },
            { key: 'polling_mode',  label: '轮询模式 polling_mode', type: 'select', options: {'0':'关闭','1':'模式 1','2':'模式 2','3':'模式 3'} },
            { key: 'ext_param',     label: '扩展参数 ext_param', type: 'text', hint: '任意透传字符串，异步通知原样返回' },
        ],
        sample: () => ({
            mch_order_no: 'JT' + Date.now(),
            way_code: 'ALI_QR',
            amount: 1,
            currency: 'cny',
            subject: '立收通 SDK Demo 商品',
            body: '立收通 SDK Demo 商品描述',
            channel_extra: '{"pay_data_type":"code_img_url"}',
            expired_time: 7200,
            division_mode: '0',
            polling_mode: '0',
            notify_url: DEFAULT_NOTIFY,
            return_url: DEFAULT_RETURN,
            client_ip: '127.0.0.1',
        }),
    },
    balancepay: {
        title: '余额支付参数（通过统一下单 ' + ENDPOINTS.balancepay + '）',
        fields: [
            { key: 'mch_order_no', label: '商户订单号 mch_order_no', required: true, type: 'text', hint: '商户生成的唯一订单号，最长 30 字符' },
            { key: 'way_code', label: '接口代码 way_code', required: true, type: 'text', hint: '固定为 BALANCE_PAY' },
            { key: 'amount', label: '支付金额 amount（分）', required: true, type: 'number', hint: '必须为大于 0 的整数' },
            { key: 'currency', label: '货币 currency', required: true, type: 'text', hint: '人民币固定为 cny' },
            { key: 'subject', label: '商品标题 subject', required: true, type: 'text' },
            { key: 'body', label: '商品描述 body', required: true, type: 'text' },
            { key: 'notify_url', label: '异步通知地址 notify_url', type: 'text' },
            { key: 'return_url', label: '同步跳转地址 return_url', type: 'text' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text' },
            { key: 'expired_time', label: '订单有效期 expired_time（秒）', type: 'number' },
            { key: 'channel_extra', label: '余额转账参数 channel_extra', required: true, type: 'text', hint: 'JSON 字符串' },
            { key: 'division_mode', label: '分账模式 division_mode', required: true, type: 'text', hint: '固定为 1' },
            { key: 'ext_param', label: '扩展参数 ext_param', type: 'text' },
        ],
        sample: () => ({
            mch_order_no: '8504920400284465',
            way_code: 'BALANCE_PAY',
            amount: 300,
            currency: 'cny',
            subject: '余额转账测试',
            body: '余额转账测试',
            notify_url: 'https://www.lspay.tech/lspay/notify',
            return_url: 'https://www.lspay.tech/success',
            channel_extra: '{"trans_out":"350873420280024268","div_list":[{"div_amt":100,"trans_in":"3709180455068368896"},{"div_amt":200,"trans_in":"3681144962280062976"}]}',
            division_mode: 1,
        }),
    },
    balancequery: {
        title: '余额查询参数（' + ENDPOINTS.balancequery + '）',
        fields: [
            { key: 'order_id', label: '商户请求流水号 order_id', required: true, type: 'text', hint: '商户生成的请求流水号，最长 30 字符' },
            { key: 'way_code', label: '接口代码 way_code', required: true, type: 'text', hint: '固定为 BALANCE_QUERY' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text', hint: '可选，仅支持 IPv4' },
            { key: 'account_no', label: '电子账号 account_no', required: true, type: 'text', hint: '用户编号，最长 256 字符' },
        ],
        sample: () => ({
            order_id: '20160427210604000490',
            way_code: 'BALANCE_QUERY',
            client_ip: '210.73.10.148',
            account_no: '313920933111042',
        }),
    },
    divisionorder: {
        title: '订单分账参数（通过统一下单 ' + ENDPOINTS.divisionorder + '）',
        fields: [
            { key: 'mch_order_no',  label: '商户订单号 mch_order_no', required: true, type: 'text' },
            { key: 'way_code',      label: '支付方式 way_code', required: true, type: 'select', options: WAY_CODES },
            { key: 'amount',        label: '订单金额 amount（分）', required: true, type: 'number' },
            { key: 'currency',      label: '货币 currency', type: 'text', hint: '默认 cny' },
            { key: 'subject',       label: '商品标题 subject', required: true, type: 'text' },
            { key: 'body',          label: '商品描述 body', required: true, type: 'text' },
            { key: 'division_mode', label: '分账模式 division_mode', required: true, type: 'select', options: {'1':'实时分账','2':'延时分账'} },
            { key: 'channel_extra', label: '分账渠道参数 channel_extra', required: true, type: 'text', hint: 'JSON 字符串' },
            { key: 'client_ip',     label: '客户端 IP client_ip', type: 'text' },
            { key: 'notify_url',    label: '异步通知地址 notify_url', type: 'text' },
            { key: 'return_url',    label: '同步跳转地址 return_url', type: 'text' },
            { key: 'expired_time',  label: '订单有效期 expired_time（秒）', type: 'number' },
            { key: 'ext_param',     label: '扩展参数 ext_param', type: 'text' },
        ],
        sample: () => ({
            mch_order_no: 'DIV' + Date.now(),
            way_code: 'ALI_QR',
            amount: 100,
            currency: 'cny',
            subject: '分账测试订单',
            body: '立收通订单分账示例',
            division_mode: '1',
            channel_extra: '{"div_list":[{"div_amt":10,"trans_in":"350873274291111","description":"经销商订单分账"},{"trans_in":"35087342028002222","description":"按配置比例分账"}],"pay_data_type":"code_img_url"}',
            notify_url: DEFAULT_NOTIFY,
            return_url: DEFAULT_RETURN,
            client_ip: '127.0.0.1',
        }),
    },
    delaytransconfirm: {
        title: '延时分账交易确认参数（' + ENDPOINTS.delaytransconfirm + '）',
        requireOne: [{ keys: ['pay_order_id', 'mch_order_no'], message: 'pay_order_id 与 mch_order_no 至少填写一个' }],
        fields: [
            { key: 'pay_order_id', label: '支付订单号 pay_order_id', type: 'text', hint: '与 mch_order_no 至少填写一个，最长 30 字符' },
            { key: 'mch_order_no', label: '商户订单号 mch_order_no', type: 'text', hint: '与 pay_order_id 至少填写一个，最长 30 字符' },
            { key: 'order_id', label: '商户确认流水号 order_id', required: true, type: 'text', hint: '商户生成且应唯一，最长 30 字符' },
            { key: 'channel_extra', label: '分账渠道参数 channel_extra', type: 'text', hint: 'JSON 字符串' },
        ],
        sample: () => ({
            pay_order_id: '202106181104177050002',
            order_id: 'CONFIRM' + Date.now(),
            channel_extra: '{"div_list":[{"div_amt":10,"trans_in":"123","description":"经销商"},{"div_amt":10,"trans_in":"456","description":"渠道商"}]}',
        }),
    },
    delaytransconfirmrefund: {
        title: '交易确认退款参数（' + ENDPOINTS.delaytransconfirmrefund + '）',
        fields: [
            { key: 'confirm_order_id', label: '确认流水号 confirm_order_id', required: true, type: 'text', hint: '交易确认流水号，最长 30 字符' },
            { key: 'confirm_refund_no', label: '退款流水号 confirm_refund_no', required: true, type: 'text', hint: '商户生成且应唯一，最长 30 字符' },
            { key: 'channel_extra', label: '渠道参数 channel_extra', type: 'text', hint: 'JSON 字符串' },
        ],
        sample: () => ({
            confirm_order_id: '27210632100491',
            confirm_refund_no: 'CREFUND' + Date.now(),
        }),
    },
    delaytransunconfirmquery: {
        title: '可转余额查询（' + ENDPOINTS.delaytransunconfirmquery + '）',
        fields: [],
        sample: () => ({}),
    },
    delaytranscreatetask: {
        title: '延时分账订单转账参数（' + ENDPOINTS.delaytranscreatetask + '）',
        fields: [
            { key: 'account_no', label: '电子账号 account_no', required: true, type: 'text', hint: '收款人电子账号，最长 30 字符' },
            { key: 'account_name', label: '账号姓名 account_name', required: true, type: 'text', hint: '收款人姓名，最长 30 字符' },
            { key: 'order_no', label: '商户流水号 order_no', required: true, type: 'text', hint: '商户生成且应唯一，最长 30 字符' },
            { key: 'amount', label: '转账金额 amount（分）', required: true, type: 'number', hint: '大于 0 的整数，单位：分' },
            { key: 'notify_url', label: '结果通知地址 notify_url', type: 'text', hint: '可选，最长 64 字符；通知验签后返回 ok' },
            { key: 'remark', label: '备注 remark', type: 'text', hint: '可选，最长 256 字符' },
        ],
        sample: () => ({
            account_no: 'YOUR_ELECTRONIC_ACCOUNT',
            account_name: '分销商',
            order_no: 'TRANSFER' + Date.now(),
            amount: 100,
            notify_url: DEFAULT_NOTIFY,
            remark: '延时分账订单转账',
        }),
    },
    quickpaybindapply: {
        title: '快捷支付绑卡申请参数（' + ENDPOINTS.quickpaybindapply + '）',
        fields: [
            { key: 'order_id', label: '商户请求流水号 order_id', required: true, type: 'text', hint: '商户生成且应唯一，最长 30 字符' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text', hint: '可选，仅支持 IPv4' },
            { key: 'channel_extra', label: '绑卡渠道参数 channel_extra', required: true, type: 'text', hint: 'JSON 字符串' },
        ],
        sample: () => ({
            order_id: 'BIND' + Date.now(),
            client_ip: '127.0.0.1',
            channel_extra: '{"mch_user_id":"TEST_USER_1","cert_type":"00","card_id":"6222020200000000000","card_name":"测试用户","cert_id":"110101199001011234","cert_validity_type":"0","cert_begin_date":"20200101","cert_end_date":"20400101","card_mp":"13800000000","dc_type":"D"}',
        }),
    },
    quickpaybindconfirm: {
        title: '快捷支付绑卡确认参数（' + ENDPOINTS.quickpaybindconfirm + '）',
        fields: [
            { key: 'order_id', label: '绑卡申请流水号 order_id', required: true, type: 'text', hint: '必须与绑卡申请时提交的流水号一致，最长 30 字符' },
            { key: 'verify_code', label: '短信验证码 verify_code', required: true, type: 'text', hint: '文档长度与示例冲突，SDK兼容 3-8 位数字' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text', hint: '可选，仅支持 IPv4' },
            { key: 'channel_extra', label: '绑卡确认渠道参数 channel_extra', required: true, type: 'text', hint: 'JSON 字符串' },
        ],
        sample: () => ({
            order_id: 'BIND' + (Date.now() - 60000),
            verify_code: '123456',
            client_ip: '127.0.0.1',
            channel_extra: '{"mch_user_id":"TEST_USER_1","cert_type":"00","card_id":"6222020200000000000","card_name":"测试用户","cert_id":"110101199001011234","card_mp":"13800000000","dc_type":"D"}',
        }),
    },
    quickpayapply: {
        title: '快捷支付申请参数（通过统一下单 ' + ENDPOINTS.quickpayapply + '）',
        fields: [
            { key: 'mch_order_no', label: '商户订单号 mch_order_no', required: true, type: 'text', hint: '商户生成且应唯一，最长 30 字符' },
            { key: 'way_code', label: '支付方式 way_code', required: true, type: 'text', hint: '固定值 BANK_FAST' },
            { key: 'amount', label: '支付金额 amount（分）', required: true, type: 'number' },
            { key: 'currency', label: '货币 currency', required: true, type: 'text', hint: '默认 cny' },
            { key: 'subject', label: '商品标题 subject', required: true, type: 'text' },
            { key: 'body', label: '商品描述 body', required: true, type: 'text' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text' },
            { key: 'notify_url', label: '异步通知地址 notify_url', type: 'text' },
            { key: 'return_url', label: '同步跳转地址 return_url', type: 'text' },
            { key: 'expired_time', label: '订单有效期 expired_time（秒）', type: 'number' },
            { key: 'channel_extra', label: '快捷支付渠道参数 channel_extra', required: true, type: 'text', hint: 'JSON 字符串' },
            { key: 'division_mode', label: '分账模式 division_mode', type: 'select', options: {'0':'不分账','1':'实时分账','2':'延时分账'} },
            { key: 'ext_param', label: '扩展参数 ext_param', type: 'text' },
        ],
        sample: () => ({
            mch_order_no: 'QPAY' + Date.now(),
            way_code: 'BANK_FAST',
            amount: 100,
            currency: 'cny',
            subject: '快捷支付测试',
            body: '快捷支付申请测试',
            client_ip: '127.0.0.1',
            notify_url: DEFAULT_NOTIFY,
            return_url: DEFAULT_RETURN,
            expired_time: 3600,
            channel_extra: '{"channel_user":"YOUR_CHANNEL_USER","channel_card_id":"YOUR_CHANNEL_CARD_ID"}',
            division_mode: '0',
        }),
    },
    quickpayconfirm: {
        title: '快捷支付确认参数（' + ENDPOINTS.quickpayconfirm + '）',
        requireOne: [{ keys: ['pay_order_id', 'mch_order_no'], message: 'pay_order_id 与 mch_order_no 至少填写一个' }],
        fields: [
            { key: 'way_code', label: '支付方式 way_code', required: true, type: 'text', hint: '固定值 BANK_FAST' },
            { key: 'pay_order_id', label: '支付订单号 pay_order_id', type: 'text', hint: '与 mch_order_no 至少填写一个，最长 30 字符' },
            { key: 'mch_order_no', label: '商户订单号 mch_order_no', type: 'text', hint: '与 pay_order_id 至少填写一个，最长 30 字符' },
            { key: 'verify_code', label: '短信验证码 verify_code', required: true, type: 'text', hint: '固定为 6 位数字' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text', hint: '可选，仅支持 IPv4' },
        ],
        sample: () => ({
            way_code: 'BANK_FAST',
            pay_order_id: 'YOUR_PAY_ORDER_ID',
            verify_code: '123456',
            client_ip: '127.0.0.1',
        }),
    },
    quickpayunbind: {
        title: '快捷支付解绑参数（' + ENDPOINTS.quickpayunbind + '）',
        fields: [
            { key: 'order_id', label: '商户请求流水号 order_id', required: true, type: 'text', hint: '商户生成且应唯一，最长 30 字符' },
            { key: 'way_code', label: '支付方式 way_code', required: true, type: 'text', hint: '固定值 BANK_FAST' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text', hint: '可选，仅支持 IPv4' },
            { key: 'channel_extra', label: '解绑渠道参数 channel_extra', required: true, type: 'text', hint: 'JSON 字符串' },
        ],
        sample: () => ({
            order_id: 'UNBIND' + Date.now(),
            way_code: 'BANK_FAST',
            client_ip: '127.0.0.1',
            channel_extra: '{"mch_user_id":"TEST_USER_1","channel_card_id":"YOUR_CHANNEL_CARD_ID"}',
        }),
    },
    quickpaysmscheck: {
        requireOne: [{ keys: ['pay_order_id', 'mch_order_no'], message: 'pay_order_id 与 mch_order_no 至少填写一个' }],
        title: '快捷支付短信校验参数（' + ENDPOINTS.quickpaysmscheck + '）',
        notice: '短信校验仅用于预校验验证码；校验通过后仍需调用“支付确认”接口完成支付。',
        fields: [
            { key: 'way_code', label: '支付方式 way_code', required: true, type: 'text', hint: '固定值 BANK_FAST' },
            { key: 'pay_order_id', label: '支付订单号 pay_order_id', type: 'text', hint: '与 mch_order_no 至少填写一个，最长 30 字符' },
            { key: 'mch_order_no', label: '商户订单号 mch_order_no', type: 'text', hint: '与 pay_order_id 至少填写一个，最长 30 字符' },
            { key: 'verify_code', label: '短信验证码 verify_code', required: true, type: 'text', hint: '固定为 6 位数字' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text', hint: '可选，仅支持 IPv4' },
        ],
        sample: () => ({
            way_code: 'BANK_FAST',
            pay_order_id: 'YOUR_PAY_ORDER_ID',
            verify_code: '123456',
            client_ip: '127.0.0.1',
        }),
    },
    electronicaccountuseropen: {
        title: '电子账户用户开通参数（' + ENDPOINTS.electronicaccountuseropen + '）',
        fields: [
            { key: 'way_code', label: '接口代码 way_code', required: true, type: 'text', hint: '固定值 APPLY' },
            { key: 'order_id', label: '商户请求流水号 order_id', required: true, type: 'text', hint: '商户生成且应唯一，最长 30 字符' },
            { key: 'account_name', label: '名称 account_name', required: true, type: 'text', hint: '个人姓名或企业/个体户名称，最长 30 字符' },
            { key: 'account_type', label: '账户类型 account_type', required: true, type: 'select', options: {'1':'个人','2':'企业','3':'个体户'} },
            { key: 'relation_type', label: '关系类型 relation_type', required: true, type: 'select', options: {'SERVICE_PROVIDER':'服务商','STORE':'门店','STAFF':'员工','STORE_OWNER':'店主','PARTNER':'合作伙伴','HEADQUARTER':'总部','BRAND':'品牌方','DISTRIBUTOR':'分销商','USER':'用户','SUPPLIER':'供应商','CUSTOM':'自定义'} },
            { key: 'relation_type_name', label: '自定义关系 relation_type_name', type: 'text', hint: 'relation_type=CUSTOM 时必填' },
            { key: 'account_alias', label: '账户别名 account_alias', required: true, type: 'text' },
            { key: 'division_profit', label: '分账比例 division_profit', required: true, type: 'text', hint: '0.0410 表示 4.10%；转账和余额支付模式填 0' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text' },
            { key: 'channel_extra', label: '用户验证参数 channel_extra', required: true, type: 'text', hint: 'JSON 字符串' },
        ],
        sample: () => ({
            way_code: 'APPLY',
            order_id: 'EACCOUNT' + Date.now(),
            account_name: '测试用户',
            account_type: '1',
            relation_type: 'USER',
            account_alias: '测试账户',
            division_profit: '0.0410',
            client_ip: '127.0.0.1',
            channel_extra: '{"user_info":{"cert_type":"00","name":"测试用户","cert_no":"110101199001011234","cert_validity_type":"0","cert_begin_date":"20200101","cert_end_date":"20400101","mobile_no":"13800000000","email":"test@example.com"}}',
        }),
    },
    electronicaccountbusinessopen: {
        title: '电子账户业务开通参数（' + ENDPOINTS.electronicaccountbusinessopen + '）',
        fields: [
            { key: 'order_id', label: '商户请求流水号 order_id', required: true, type: 'text', hint: '商户生成且应唯一，最长 30 字符' },
            { key: 'account_no', label: '用户编号 account_no', required: true, type: 'text', hint: '用户开通成功后返回的电子账户编号，最长 32 字符' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text', hint: '可选，仅支持 IPv4' },
            { key: 'channel_extra', label: '业务配置 channel_extra', required: true, type: 'text', hint: 'JSON 字符串' },
        ],
        sample: () => ({
            order_id: 'EBUSINESS' + Date.now(),
            account_no: 'YOUR_ELECTRONIC_ACCOUNT_NO',
            client_ip: '127.0.0.1',
            channel_extra: '{"card_info":{"card_type":"1","card_name":"测试用户","card_no":"6222020200000000000","prov_id":"100000","area_id":"110000","cert_type":"00","cert_no":"110101199001011234","cert_validity_type":"0","cert_begin_date":"20200101","cert_end_date":"20400101","mp":"13800000000"},"settle_config":{"settle_cycle":"D1","settle_status":1},"cash_config":[{"cash_type":"D1","switch_state":1,"out_fee_flag":1},{"cash_type":"DM","switch_state":1,"out_fee_flag":1}]}',
        }),
    },
    electronicaccountbusinesschange: {
        title: '电子账户业务变更参数（' + ENDPOINTS.electronicaccountbusinesschange + '）',
        fields: [
            { key: 'order_id', label: '商户请求流水号 order_id', required: true, type: 'text', hint: '商户生成且应唯一，最长 30 字符' },
            { key: 'account_no', label: '用户编号 account_no', required: true, type: 'text', hint: '用户开通成功后返回的电子账户编号，最长 32 字符' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text', hint: '可选，仅支持 IPv4' },
            { key: 'channel_extra', label: '变更配置 channel_extra', required: true, type: 'text', hint: 'JSON 字符串' },
        ],
        sample: () => ({
            order_id: 'ECHANGE' + Date.now(),
            account_no: 'YOUR_ELECTRONIC_ACCOUNT_NO',
            client_ip: '127.0.0.1',
            channel_extra: '{"card_info":{"card_type":"1","card_name":"测试用户","card_no":"6222020200000000000"},"settle_config":{"settle_cycle":"D1","settle_status":1},"cash_config":[{"cash_type":"D1","switch_state":1,"out_fee_flag":1},{"cash_type":"DM","switch_state":1,"out_fee_flag":1}]}',
        }),
    },
    electronicaccountuserquery: {
        title: '电子账户用户查询参数（' + ENDPOINTS.electronicaccountuserquery + '）',
        fields: [
            { key: 'account_no', label: '用户编号 account_no', required: true, type: 'text', hint: '用户开通成功后返回的电子账户编号，最长 32 字符' },
            { key: 'client_ip', label: '客户端 IP client_ip', type: 'text', hint: '可选，仅支持 IPv4' },
        ],
        sample: () => ({
            account_no: 'YOUR_ELECTRONIC_ACCOUNT_NO',
            client_ip: '127.0.0.1',
        }),
    },
    electronicaccountsettlementquery: {
        title: '电子账户结算记录查询参数（' + ENDPOINTS.electronicaccountsettlementquery + '）',
        fields: [
            { key: 'order_id', label: '请求流水号 order_id', required: true, type: 'text', hint: '商户生成且应唯一，最长 30 字符' },
            { key: 'way_code', label: '支付方式 way_code', required: true, type: 'text', hint: '固定为 SETTLE_QUERY' },
            { key: 'account_no', label: '用户编号 account_no', required: true, type: 'text', hint: '用户开通成功后返回的电子账户编号，最长 32 字符' },
            { key: 'begin_date', label: '开始日期 begin_date', required: true, type: 'text', hint: 'yyyyMMdd，与结束日期跨度不超过 30 天' },
            { key: 'end_date', label: '结束日期 end_date', required: true, type: 'text', hint: 'yyyyMMdd，与开始日期跨度不超过 30 天' },
            { key: 'page_size', label: '每页条数 page_size', type: 'number', hint: '可选，默认 10，建议不超过 100' },
            { key: 'page_num', label: '当前页码 page_num', type: 'number', hint: '可选，从 1 开始' },
        ],
        sample: () => ({
            order_id: '20160427210604000490',
            way_code: 'SETTLE_QUERY',
            account_no: '2123214353453456564',
            begin_date: '20250401',
            end_date: '20250409',
            page_size: 10,
            page_num: 1,
        }),
    },
    electronicaccountbalancewithdraw: {
        title: '电子账户余额提现参数（' + ENDPOINTS.electronicaccountbalancewithdraw + '）',
        fields: [
            { key: 'order_id', label: '请求流水号 order_id', required: true, type: 'text', hint: '商户生成的唯一请求标识，最长 30 字符' },
            { key: 'way_code', label: '支付方式 way_code', required: true, type: 'text', hint: '固定为 BALANCE_WITHDRAW' },
            { key: 'account_no', label: '用户编号 account_no', required: true, type: 'text', hint: '用户开通成功后返回的电子账户编号，最长 32 字符' },
            { key: 'withdraw_amount', label: '提现金额 withdraw_amount（元）', required: true, type: 'text', hint: '必须大于 0，最多保留 2 位小数' },
        ],
        sample: () => ({
            order_id: '20160427210604000490',
            way_code: 'BALANCE_WITHDRAW',
            account_no: '2123214353453456564',
            withdraw_amount: '99.99',
        }),
    },
    electronicaccountsinglewithdrawquery: {
        title: '电子账户单笔提现查询参数（' + ENDPOINTS.electronicaccountsinglewithdrawquery + '）',
        fields: [
            { key: 'way_code', label: '支付方式 way_code', required: true, type: 'text', hint: '固定为 WITHDRAW_SINGLE_QUERY' },
            { key: 'account_no', label: '用户编号 account_no', required: true, type: 'text', hint: '用户开通成功后返回的电子账户编号，最长 32 字符' },
            { key: 'withdraw_date', label: '提现日期 withdraw_date', required: true, type: 'text', hint: 'yyyyMMdd，可以查询当天' },
            { key: 'order_id', label: '原请求流水号 order_id', required: true, type: 'text', hint: '余额提现时使用的请求流水号，最长 30 字符' },
        ],
        sample: () => ({
            way_code: 'WITHDRAW_SINGLE_QUERY',
            account_no: '2123214353453456564',
            withdraw_date: '20250401',
            order_id: '20250409123144123',
        }),
    },
    electronicaccountbatchwithdrawquery: {
        title: '电子账户批量提现查询参数（' + ENDPOINTS.electronicaccountbatchwithdrawquery + '）',
        fields: [
            { key: 'way_code', label: '支付方式 way_code', required: true, type: 'text', hint: '固定为 WITHDRAW_QUERY' },
            { key: 'account_no', label: '用户编号 account_no', required: true, type: 'text', hint: '电子账户编号，最长 32 字符' },
            { key: 'begin_date', label: '开始日期 begin_date', required: true, type: 'text', hint: 'yyyyMMdd；文档要求跨度小于 3 天，SDK 不做限制' },
            { key: 'end_date', label: '结束日期 end_date', required: true, type: 'text', hint: 'yyyyMMdd；开始日期不能晚于结束日期' },
            { key: 'page_size', label: '每页条数 page_size', type: 'number', hint: '可选，默认 10，建议不超过 100' },
            { key: 'page_num', label: '当前页码 page_num', type: 'number', hint: '可选，从 1 开始' },
        ],
        sample: () => ({
            way_code: 'WITHDRAW_QUERY',
            account_no: '2123214353453456564',
            begin_date: '20250401',
            end_date: '20250409',
            page_size: 10,
            page_num: 1,
        }),
    },
    queryorder: {
        title: '订单查询参数（' + ENDPOINTS.queryorder + '）',
        requireOne: [{ keys: ['mch_order_no', 'pay_order_id'], message: 'mch_order_no 与 pay_order_id 至少填写一个' }],
        fields: [
            { key: 'mch_order_no', label: '商户订单号 mch_order_no', type: 'text', hint: '与 pay_order_id 至少填写一个，最长 30 字符' },
            { key: 'pay_order_id', label: '支付订单号 pay_order_id', type: 'text', hint: '支付中心生成，最长 30 字符' },
        ],
        sample: () => ({ mch_order_no: 'JT' + (Date.now() - 86400000) }),
    },
    refund: {
        title: '申请退款参数（' + ENDPOINTS.refund + '）',
        requireOne: [{ keys: ['mch_order_no', 'pay_order_id'], message: 'mch_order_no 与 pay_order_id 至少填写一个' }],
        fields: [
            { key: 'mch_refund_no', label: '商户退款单号 mch_refund_no', required: true, type: 'text', hint: '最长 30 字符' },
            { key: 'mch_order_no',  label: '原商户订单号 mch_order_no', type: 'text', hint: '与 pay_order_id 至少填写一个' },
            { key: 'pay_order_id',  label: '原支付订单号 pay_order_id', type: 'text' },
            { key: 'refund_amount', label: '退款金额（分）', required: true, type: 'number' },
            { key: 'currency',      label: '货币 currency', required: true, type: 'text', hint: '默认 cny' },
            { key: 'refund_reason', label: '退款原因 refund_reason', required: true, type: 'text', hint: '最长 64 字符' },
            { key: 'client_ip',     label: '客户端 IP client_ip', type: 'text' },
            { key: 'notify_url',    label: '退款异步通知地址', type: 'text' },
            { key: 'channel_extra', label: '渠道扩展参数 channel_extra', type: 'text', hint: 'JSON 字符串' },
            { key: 'ext_param',     label: '扩展参数 ext_param', type: 'text', hint: '回调原样返回，最长 512 字符' },
        ],
        sample: () => ({
            mch_refund_no: 'JR' + Date.now(),
            mch_order_no:  'JT' + (Date.now() - 86400000),
            refund_amount: 1,
            currency: 'cny',
            refund_reason: '用户申请退款',
            client_ip: '127.0.0.1',
            ext_param: 'refund-demo',
            notify_url: DEFAULT_NOTIFY,
        }),
    },
    queryrefund: {
        title: '退款查询参数（' + ENDPOINTS.queryrefund + '）',
        requireOne: [{ keys: ['mch_refund_no', 'order_refund_id'], message: 'mch_refund_no 与 order_refund_id 至少填写一个' }],
        fields: [
            { key: 'mch_refund_no',   label: '商户退款单号 mch_refund_no', type: 'text', hint: '与 order_refund_id 至少填写一个，最长 30 字符' },
            { key: 'order_refund_id', label: '退款订单号 order_refund_id', type: 'text', hint: '支付中心生成，最长 30 字符' },
        ],
        sample: () => ({ mch_refund_no: 'JR' + (Date.now() - 86400000) }),
    },
};

let currentAction = 'unifiedorder';

function $(sel) { return document.querySelector(sel); }
function $all(sel) { return document.querySelectorAll(sel); }

function renderForm(action) {
    const meta = formMeta[action];
    $('#formTitle').textContent = meta.title;
    const root = $('#formContainer');
    root.innerHTML = '';
    renderCommonRequestFields(root);
    if (meta.notice) {
        const notice = document.createElement('div');
        notice.className = 'form-notice';
        notice.textContent = meta.notice;
        root.appendChild(notice);
    }
    if (meta.fields.length) {
        const sectionTitle = document.createElement('div');
        sectionTitle.className = 'form-section-title';
        sectionTitle.textContent = '业务请求参数';
        root.appendChild(sectionTitle);
    }
    meta.fields.forEach(f => {
        const row = document.createElement('div');
        row.className = 'form-row';
        const lbl = document.createElement('label');
        lbl.textContent = f.label;
        if (f.required) {
            const r = document.createElement('span');
            r.className = 'req'; r.textContent = ' *';
            lbl.appendChild(r);
        }
        row.appendChild(lbl);

        let input;
        if (f.type === 'select') {
            input = document.createElement('select');
            const empty = document.createElement('option');
            empty.value = ''; empty.textContent = '请选择';
            input.appendChild(empty);
            Object.keys(f.options).forEach(k => {
                const opt = document.createElement('option');
                opt.value = k; opt.textContent = k + ' - ' + f.options[k];
                input.appendChild(opt);
            });
        } else if (f.type === 'textarea' || f.key === 'channel_extra') {
            input = document.createElement('textarea');
            input.rows = 3;
            input.spellcheck = false;
        } else {
            input = document.createElement('input');
            input.type = f.type === 'number' ? 'number' : 'text';
        }
        if (f.generated) {
            input.disabled = true;
            input.value = f.value || '';
            input.placeholder = f.placeholder || 'SDK 自动注入';
        } else {
            input.name = f.key;
            input.dataset.key = f.key;
        }
        row.appendChild(input);
        if (f.presets) renderPayDataTypePresets(row, input, f.presets);

        if (f.hint) {
            const h = document.createElement('div');
            h.className = 'hint'; h.textContent = f.hint;
            row.appendChild(h);
        }
        root.appendChild(row);
    });
}

function renderPayDataTypePresets(row, input, presets) {
    const list = document.createElement('div');
    list.className = 'preset-list';
    presets.forEach(p => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'preset-btn';
        btn.dataset.value = p.value;
        btn.dataset.desc = p.desc;
        btn.textContent = p.value;
        btn.addEventListener('click', () => {
            let extra = {};
            try {
                const parsed = JSON.parse(input.value || '{}');
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) extra = parsed;
            } catch (e) { /* 非法 JSON 时直接覆盖 */ }
            extra.pay_data_type = p.value;
            input.value = JSON.stringify(extra);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        list.appendChild(btn);
    });
    const desc = document.createElement('div');
    desc.className = 'preset-desc';
    row.appendChild(list);
    row.appendChild(desc);
    input.addEventListener('input', () => syncPayDataTypePresets(row));
    syncPayDataTypePresets(row);
}

function syncPayDataTypePresets(row) {
    const input = row.querySelector('textarea, input');
    let current = '';
    try { current = String(JSON.parse(input.value).pay_data_type || ''); } catch (e) { /* 未填或非法 JSON */ }
    let desc = '';
    row.querySelectorAll('.preset-btn').forEach(btn => {
        const active = btn.dataset.value === current;
        btn.classList.toggle('active', active);
        if (active) desc = btn.dataset.desc;
    });
    row.querySelector('.preset-desc').textContent = desc;
}

function syncAllPresets() {
    $all('#formContainer .preset-list').forEach(list => syncPayDataTypePresets(list.closest('.form-row')));
}

function commonDefaultValues() {
    const profile = $('#profile').value;
    const common = PROFILE_COMMON[profile] || {};
    return {
        mch_no: common.mch_no || '',
        app_id: common.app_id || '',
        req_time: String(Date.now()),
        version: '1.0',
        sign_type: 'MD5',
        sign: '',
    };
}

function applyCommonDefaults(onlyAuto) {
    const values = commonDefaultValues();
    COMMON_REQUEST_FIELDS.forEach(field => {
        const el = fieldInput(field.key);
        if (!el) return;
        if (onlyAuto && el.dataset.auto !== '1') return;
        el.value = values[field.key];
        el.dataset.auto = '1';
    });
}

function renderCommonRequestFields(root) {
    const details = document.createElement('details');
    details.className = 'param-guide';
    details.open = true;

    const summary = document.createElement('summary');
    summary.textContent = '公共请求参数';
    details.appendChild(summary);

    const body = document.createElement('div');
    body.className = 'param-guide-body';

    const values = commonDefaultValues();
    COMMON_REQUEST_FIELDS.forEach(field => {
        const row = document.createElement('div');
        row.className = 'form-row';
        const label = document.createElement('label');
        label.textContent = field.label;
        if (field.required) {
            const mark = document.createElement('span');
            mark.className = 'req';
            mark.textContent = ' *';
            label.appendChild(mark);
        }
        row.appendChild(label);
        const input = document.createElement('input');
        input.type = 'text';
        input.name = field.key;
        input.dataset.key = field.key;
        input.dataset.auto = '1';
        input.value = values[field.key];
        if (field.placeholder) input.placeholder = field.placeholder;
        row.appendChild(input);
        if (field.hint) {
            const hint = document.createElement('div');
            hint.className = 'hint';
            hint.textContent = field.hint;
            row.appendChild(hint);
        }
        body.appendChild(row);
    });
    details.appendChild(body);
    root.appendChild(details);
}

function collectParams() {
    const params = {};
    $all('#formContainer input, #formContainer select, #formContainer textarea').forEach(el => {
        if (el.value !== '' && el.value !== null) {
            params[el.dataset.key] = el.value;
        }
    });
    return params;
}

function clearFieldErrors() {
    $all('#formContainer .form-row.has-error').forEach(row => {
        row.classList.remove('has-error');
        const err = row.querySelector('.field-error');
        if (err) err.remove();
    });
}

function missingRequiredFields() {
    const meta = formMeta[currentAction];
    const missing = [];
    COMMON_REQUEST_FIELDS.forEach(field => {
        if (!field.required) return;
        const el = fieldInput(field.key);
        const value = el ? String(el.value).trim() : '';
        if (value === '') missing.push({ field: field, el: el });
    });
    (meta.fields || []).forEach(field => {
        if (!field.required) return;
        const el = fieldInput(field.key);
        const value = el ? String(el.value).trim() : '';
        if (value === '') missing.push({ field: field, el: el });
    });
    (meta.requireOne || []).forEach(group => {
        const filled = group.keys.some(key => {
            const el = fieldInput(key);
            return el && String(el.value).trim() !== '';
        });
        if (filled) return;
        group.keys.forEach(key => {
            missing.push({ field: { key: key }, el: fieldInput(key), message: group.message });
        });
    });
    if (currentAction === 'electronicaccountuseropen') {
        const relationType = fieldInput('relation_type');
        const relationName = fieldInput('relation_type_name');
        if (relationType && relationType.value === 'CUSTOM'
            && (!relationName || String(relationName.value).trim() === '')) {
            missing.push({
                field: { key: 'relation_type_name' },
                el: relationName,
                message: 'relation_type=CUSTOM 时 relation_type_name 必填',
            });
        }
    }
    return missing;
}

function fieldInput(key) {
    return document.querySelector('#formContainer [data-key="' + key + '"]');
}

function showRequiredErrors(missing) {
    missing.forEach(item => {
        if (!item.el) return;
        const row = item.el.closest('.form-row');
        if (!row || row.querySelector('.field-error')) return;
        row.classList.add('has-error');
        const tip = document.createElement('div');
        tip.className = 'field-error';
        tip.textContent = item.message || '必填';
        row.appendChild(tip);
    });
    if (missing[0] && missing[0].el) {
        missing[0].el.focus();
        missing[0].el.scrollIntoView({ block: 'nearest' });
    }
}

function fillSample() {
    const sample = formMeta[currentAction].sample();
    Object.keys(sample).forEach(k => {
        const el = document.querySelector(`#formContainer [data-key="${k}"]`);
        if (!el) return;
        el.value = sample[k];
        delete el.dataset.auto;
    });
    syncAllPresets();
}

function clearForm() {
    $all('#formContainer input, #formContainer select, #formContainer textarea').forEach(el => {
        el.value = '';
        delete el.dataset.auto;
    });
    applyCommonDefaults(false);
    syncAllPresets();
}

function switchTab(action) {
    currentAction = action;
    $all('.tab').forEach(t => t.classList.toggle('active', t.dataset.action === action));
    renderForm(action);
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]));
}
function highlightJson(obj, highlightKey) {
    if (obj === null || obj === undefined) return '<span class="null">null</span>';
    const text = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2);
    let h = escapeHtml(text)
        .replace(/("([^"\\]|\\.)*")(\s*):/g, '<span class="k">$1</span>$3:')
        .replace(/:\s*("([^"\\]|\\.)*")/g, ': <span class="s">$1</span>')
        .replace(/:\s*(-?\d+(?:\.\d+)?)/g, ': <span class="n">$1</span>')
        .replace(/:\s*(true|false)/g, ': <span class="b">$1</span>')
        .replace(/:\s*null/g, ': <span class="null">null</span>');
    if (highlightKey) {
        const re = new RegExp('("' + highlightKey + '")', 'g');
        h = h.replace(re, '<span class="sign-hi">$1</span>');
    }
    return h;
}

function renderResult(data) {
    const root = $('#resultContainer');
    const statusBar = $('#statusBar');

    let badge;
    if (!data.ok) badge = '<span class="badge badge-err">' + (data.dry_run ? '未发送 · 本地校验失败' : 'ERROR') + '</span>';
    else if (data.cashier_form) badge = '<span class="badge badge-dry">收银台表单已生成 · 未提交</span>';
    else if (data.dry_run) badge = '<span class="badge badge-dry">未发送 · 模拟响应</span>';
    else badge = '<span class="badge badge-ok">SUCCESS</span>';
    statusBar.innerHTML = badge;

    let html = '';
    html += '<div class="result-block">';
    html += '  <div class="kv-line"><span class="k">URL</span><span class="v">' + escapeHtml(data.request_url || '-') + '</span></div>';
    html += '  <div class="kv-line"><span class="k">sign</span><span class="v">' + escapeHtml(data.sign || '-') + '</span></div>';
    html += '</div>';

    html += '<div class="result-block">';
    html += '  <div class="lab">请求参数（已注入公共字段并签名）</div>';
    html += '  <pre class="json">' + highlightJson(data.request_data, 'sign') + '</pre>';
    html += '</div>';

    if (data.error_info) {
        html += '<div class="result-block">';
        html += '  <div class="lab">错误信息</div>';
        html += '  <div style="color:#b91c1c;font-family:Consolas,Menlo,monospace;font-size:13px;background:#fee2e2;padding:8px 12px;border-radius:5px;">' + escapeHtml(data.error_info) + '</div>';
        html += '</div>';
    }

    if (data.cashier_form) {
        html += '<div class="result-block">';
        html += '  <div class="lab">收银台表单</div>';
        html += '  <div class="form-notice" style="margin-bottom:8px;">pay_data_type=cashier 需用 form 表单把上方签名参数 POST 到网关，网关直接返回收银台页面，不返回 JSON。商户正式接入时输出自动提交的表单，浏览器直接跳转收银台，无需用户再点击；面板多这一步只为防止调试误下单。'
            + (data.dry_run ? '当前是干跑模式，不会提交。' : '点击下方按钮会在新标签页打开收银台，并真实创建订单。') + '</div>';
        html += '  <button type="button" class="btn btn-primary" id="btnCashierSubmit"' + (data.dry_run ? ' disabled' : '') + '>'
            + (data.dry_run ? '干跑模式不提交' : '提交跳转收银台') + '</button>';
        html += '</div>';
        root.innerHTML = html;
        const btn = $('#btnCashierSubmit');
        if (!data.dry_run) btn.addEventListener('click', () => submitCashierForm(data.cashier_form));
        return;
    }

    html += '<div class="result-block">';
    if (data.dry_run) {
        html += '  <div class="lab">模拟响应</div>';
        html += '  <div class="form-notice" style="margin-bottom:8px;">干跑模式没有发送请求，以下是本地模拟数据，不代表商户号、应用 ID、密钥或业务参数校验通过。</div>';
    } else {
        html += '  <div class="lab">响应数据</div>';
    }
    html += '  <pre class="json">' + highlightJson(data.response_json || data.response_raw || '(空)') + '</pre>';
    html += '</div>';

    root.innerHTML = html;
}

function submitCashierForm(cashier) {
    const form = document.createElement('form');
    form.method = cashier.method;
    form.action = cashier.action;
    form.target = '_blank';
    form.style.display = 'none';
    Object.keys(cashier.fields).forEach(name => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = cashier.fields[name];
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
}

function showDialog({ title, message, confirmText = '确定', cancelText = '取消', danger = false }) {
    return new Promise(resolve => {
        const overlay = document.createElement('div');
        overlay.className = 'dialog-overlay';
        const box = document.createElement('div');
        box.className = 'dialog';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        const h = document.createElement('div');
        h.className = 'dialog-title';
        h.textContent = title;
        const p = document.createElement('div');
        p.className = 'dialog-message';
        p.textContent = message;
        const actions = document.createElement('div');
        actions.className = 'dialog-actions';
        box.append(h, p, actions);
        overlay.appendChild(box);

        const close = result => {
            document.removeEventListener('keydown', onKey);
            overlay.remove();
            resolve(result);
        };
        const onKey = e => {
            if (e.key === 'Escape') close(false);
        };
        let cancelBtn = null;
        if (cancelText) {
            cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-secondary';
            cancelBtn.textContent = cancelText;
            cancelBtn.addEventListener('click', () => close(false));
            actions.appendChild(cancelBtn);
        }
        const okBtn = document.createElement('button');
        okBtn.type = 'button';
        okBtn.className = 'btn ' + (danger ? 'btn-warn' : 'btn-primary');
        okBtn.textContent = confirmText;
        okBtn.addEventListener('click', () => close(true));
        actions.appendChild(okBtn);
        overlay.addEventListener('click', e => { if (e.target === overlay) close(false); });
        document.addEventListener('keydown', onKey);
        document.body.appendChild(overlay);
        (cancelBtn || okBtn).focus();
    });
}

async function send() {
    clearFieldErrors();
    const missing = missingRequiredFields();
    if (missing.length) {
        showRequiredErrors(missing);
        $('#statusBar').textContent = '有必填项未填写';
        return;
    }
    const reqTime = fieldInput('req_time');
    if (reqTime && reqTime.dataset.auto === '1') {
        reqTime.value = String(Date.now());
    }
    const profile = $('#profile').value;
    const dryRun  = $('#dryRun').checked;
    const params  = collectParams();
    if (!dryRun) {
        if (!ALLOW_LIVE_REQUESTS) {
            await showDialog({
                title: '真实请求未开启',
                message: '请在 config.json 中设置 "debug_panel_allow_live": true 后刷新页面。',
                confirmText: '知道了',
                cancelText: '',
            });
            return;
        }
        const ok = await showDialog({
            title: '发送真实请求',
            message: '即将向立收通发送真实请求，可能产生支付、退款、提现或转账。确认继续？',
            confirmText: '确认发送',
            cancelText: '取消',
            danger: true,
        });
        if (!ok) return;
    }
    $('#statusBar').innerHTML = '<span style="color:#95a3b8;">发送中…</span>';
    fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: currentAction,
            profile,
            dry_run: dryRun,
            params,
            confirm_live: dryRun ? '' : 'SEND_LIVE_REQUEST',
        }),
    })
    .then(r => r.json())
    .then(renderResult)
    .catch(e => {
        $('#statusBar').innerHTML = '<span class="badge badge-err">FAIL</span>';
        $('#resultContainer').innerHTML = '<div style="color:#b91c1c;">请求异常: ' + escapeHtml(e.message) + '</div>';
    });
}

// 绑定
$('#tabList').addEventListener('click', e => {
    const tab = e.target.closest('.tab');
    if (tab) switchTab(tab.dataset.action);
});
$('#profile').addEventListener('change', () => applyCommonDefaults(true));
$('#formContainer').addEventListener('input', e => {
    if (e.target && e.target.dataset) delete e.target.dataset.auto;
    const row = e.target.closest('.form-row');
    if (!row) return;
    row.classList.remove('has-error');
    const err = row.querySelector('.field-error');
    if (err) err.remove();
});
$('#formContainer').addEventListener('change', e => {
    const row = e.target.closest('.form-row');
    if (!row) return;
    row.classList.remove('has-error');
    const err = row.querySelector('.field-error');
    if (err) err.remove();
});
$('#btnFill').addEventListener('click', fillSample);
$('#btnSend').addEventListener('click', send);
$('#btnReset').addEventListener('click', clearForm);

// 初始化
renderForm(currentAction);
</script>

</body>
</html>
