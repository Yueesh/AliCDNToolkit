<?php

// PHP 8.0 错误报告设置 - 生产环境
error_reporting(E_ALL);
ini_set('display_errors', 0);  // 关闭错误显示
ini_set('log_errors', 1);       // 开启错误日志

// 引入配置
require_once __DIR__ . '/../vendor/autoload.php';
// 不要在这里加载配置文件，让控制器自己加载

// 启动会话
session_start();

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 主要处理逻辑
try {
    // 引入错误处理器
    require_once __DIR__ . '/../app/helpers/ErrorHandler.php';

    // 初始化错误处理
    App\Helpers\ErrorHandler::init();

    // 引入核心文件
    require_once __DIR__ . '/../app/services/CdnService.php';
    require_once __DIR__ . '/../app/controllers/DomainController.php';

    // 处理请求
    $controller = new App\Controllers\DomainController();
    $action = $_GET['action'] ?? 'index';

    switch ($action) {
        case 'index':
            $controller->index();
            break;
        case 'refresh':
            $controller->refresh();
            break;
        case 'batchUpdate':
            $controller->batchUpdate();
            break;
        case 'logout':
            $controller->logout();
            break;
        case 'authenticate':
            $controller->authenticate();
            break;
        default:
            $controller->index();
            break;
    }

} catch (Exception $e) {
    // 捕获所有未处理的异常
    if ($debug_mode) {
        // 调试模式显示详细错误
        echo '<div style="font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px;">';
        echo '<h1 style="color: #e74c3c;">系统错误（调试模式）</h1>';
        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #e74c3c;">';
        echo '<p><strong>错误信息:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>错误文件:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
        echo '<p><strong>错误行号:</strong> ' . $e->getLine() . '</p>';
        echo '</div>';

        echo '<h3>堆栈跟踪:</h3>';
        echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto; font-size: 12px;">';
        echo htmlspecialchars($e->getTraceAsString());
        echo '</pre>';

        echo '<h3>调试建议:</h3>';
        echo '<ul>';
        echo '<li>检查是否已运行 <code>composer install</code></li>';
        echo '<li>确认所有必需文件存在</li>';
        echo '<li>检查PHP扩展是否正确安装</li>';
        echo '<li>查看服务器错误日志</li>';
        echo '</ul>';

        echo '<p><a href="?debug=1">开启调试模式</a> | <a href="simple.php">使用简化版本</a> | <a href="debug.php">运行调试脚本</a></p>';
        echo '</div>';
    } else {
        // 生产模式显示友好错误页面
        echo '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center;">';
        echo '<h1 style="color: #e74c3c;">系统错误</h1>';
        echo '<p>抱歉，系统出现了错误，请稍后重试。</p>';
        echo '<p><a href="?debug=1">开启调试模式</a></p>';
        echo '<p><a href="simple.php">使用简化版本</a></p>';
        echo '<p><a href="debug.php">运行调试脚本</a></p>';
        echo '</div>';
    }

    // 记录错误到日志
    if (function_exists('error_log')) {
        $log_message = sprintf(
            "[%s] %s in %s:%d",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        error_log($log_message . "\n" . $e->getTraceAsString(), 3, __DIR__ . '/../logs/error.log');
    }
}