<?php
/**
 * Composer 与手工加载共用的 SDK 常量初始化。
 */

ini_set('date.timezone', 'Asia/Shanghai');

if (!defined('SDK_BASE')) {
    define('SDK_BASE', __DIR__);
}
if (!defined('SDK_VERSION')) {
    define('SDK_VERSION', 'php#v3.0.0');
}
if (!defined('API_VERSION')) {
    define('API_VERSION', '1.0');
}
if (!defined('DEBUG')) {
    define('DEBUG', false);
}
if (!defined('LOG')) {
    define('LOG', dirname(SDK_BASE) . '/log');
}
if (!defined('PROD_MODE')) {
    define('PROD_MODE', true);
}
