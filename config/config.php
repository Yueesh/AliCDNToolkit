<?php

// 配置文件
return [
    'app' => [
        'name' => 'CDN域名管理系统',
        'version' => '1.0.0',
        'timezone' => 'Asia/Shanghai',
        'environment' => 'production', // production 或 development
    ],
    'php' => [
        'display_errors' => false,
        'error_reporting' => E_ALL,
        'memory_limit' => '256M',
        'max_execution_time' => 300, // 5分钟
    ],
    'session' => [
        'name' => 'CDN_SESSION',
        'lifetime' => 3600, // 1小时
        'secure' => false, // HTTPS时设为true
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    'cdn' => [
        'api_version' => '2014-11-11',
        'endpoint' => 'cdn.aliyuncs.com',
        'timeout' => 30,
        'retry_count' => 3,
        'retry_delay' => 100000, // 微秒
    ],
    'security' => [
        'csrf_token_name' => 'csrf_token',
        'session_key_prefix' => 'cdn_',
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15分钟
    ],
    'logging' => [
        'enabled' => true,
        'log_level' => 'ERROR', // DEBUG, INFO, WARNING, ERROR
        'log_file' => '../logs/app.log',
    ]
];