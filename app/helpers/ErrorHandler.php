<?php

namespace App\Helpers;

class ErrorHandler
{
    /**
     * 自定义错误处理器
     */
    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        // 不处理被@抑制的错误
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // 将错误转换为异常
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * 异常处理器
     */
    public static function handleException($exception)
    {
        // 记录错误日志
        $logMessage = sprintf(
            "[%s] %s in %s:%d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        error_log($logMessage, 3, __DIR__ . '/../../logs/error.log');

        // 如果是AJAX请求，返回JSON错误
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => '系统错误，请稍后重试'
            ]);
        } else {
            // 显示友好的错误页面
            echo '<div style="padding: 20px; max-width: 600px; margin: 50px auto; font-family: Arial, sans-serif;">';
            echo '<h2 style="color: #e74c3c;">系统错误</h2>';
            echo '<p>抱歉，系统出现了错误，请稍后重试。</p>';
            echo '<p><a href="?action=index">返回首页</a></p>';

            // 开发环境显示详细错误
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                echo '<hr>';
                echo '<h3>错误详情（开发环境）</h3>';
                echo '<p><strong>错误:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>';
                echo '<p><strong>文件:</strong> ' . htmlspecialchars($exception->getFile()) . '</p>';
                echo '<p><strong>行号:</strong> ' . $exception->getLine() . '</p>';
                echo '<pre style="background: #f8f9fa; padding: 10px; overflow: auto;">' .
                     htmlspecialchars($exception->getTraceAsString()) . '</pre>';
            }
            echo '</div>';
        }

        exit;
    }

    /**
     * 初始化错误处理
     */
    public static function init()
    {
        // 设置错误处理器
        set_error_handler([self::class, 'handleError']);

        // 设置异常处理器
        set_exception_handler([self::class, 'handleException']);

        // 确保日志目录存在
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
}