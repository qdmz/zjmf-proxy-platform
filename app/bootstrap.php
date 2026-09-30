<?php
/**
 * 智简魔方代理销售平台 - 应用启动器
 */
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
date_default_timezone_set('Asia/Shanghai');

define('APP_ROOT', dirname(__DIR__));

session_name('zjmf_sid');
session_start();

// PSR-4 自动加载（无需 composer）
spl_autoload_register(function (string $class) {
    if (strpos($class, 'App\\') === 0) {
        $file = APP_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// 全局辅助函数
require APP_ROOT . '/app/Core/helpers.php';

// 配置（安装前可能不存在，此时使用默认值）
$configFile = APP_ROOT . '/config/config.php';
if (file_exists($configFile)) {
    \App\Core\Config::load($configFile);
}

// 视图目录
\App\Core\View::$baseDir = APP_ROOT . '/resources/views';

// 全局错误捕获
set_exception_handler(function (\Throwable $e) {
    \App\Core\Logger::log('EXCEPTION: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false || is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        // 与 json_fail 保持同一格式（code/message），否则前端读不到错误信息
        echo json_encode(['code' => 500, 'message' => '服务器内部错误: ' . $e->getMessage(), 'data' => null], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<h1>系统繁忙</h1><p>请稍后再试。</p>';
    }
    exit;
});
