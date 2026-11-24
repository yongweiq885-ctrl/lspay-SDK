<?php
/**
 * Demo 公共加载器
 *
 *   每个 Demo 文件只需：
 *       require_once dirname(__FILE__) . "/loader.php";
 *   即可获得：
 *     - SDK 核心类全部加载
 *     - 商户配置已就位（来自 config/config.json）
 *     - DemoConstants 演示参数可用
 */
namespace LsPayDemo;

use LsPaySdk\core\LsPay;

// 1. 覆盖 SDK 初始化常量（必须在 require init.php 之前）
if (!defined("DEBUG"))     define("DEBUG",     true);                        // 打开调试日志
if (!defined("LOG"))       define("LOG",       dirname(__FILE__) . "/log");  // 日志目录
if (!defined("PROD_MODE")) define("PROD_MODE", true);                        // true=生产 / false=沙箱

// 2. 加载 SDK
require_once dirname(__FILE__) . '/../LsPaySdk/init.php';

// 3. 加载 Demo 常量
require_once dirname(__FILE__) . '/constants/DemoConstants.php';

// 4. 加载默认商户配置；未创建本地配置文件时可完全使用 LSPAY_* 环境变量。
$configFile = dirname(__FILE__) . '/config/config.json';
if (file_exists($configFile)) {
    LsPay::init($configFile);
} else {
    LsPay::init([], true);
}

// 5. 多商户场景示例（如不需要，注释掉即可）
//    先复制 config/config_merchant_b.example.json 为 config_merchant_b.json 并填写真实配置
//    使用：new LsPayClient("merch_b") 创建的实例就会用第二份商户配置
// LsPay::init(dirname(__FILE__) . '/config/config_merchant_b.json', false, 'merch_b');
