<?php

namespace App\Controllers;

use App\Services\CdnService;
use Exception;

class SimpleDomainController
{
    private $cdnService;
    private $config;

    public function __construct()
    {
        // 使用硬编码的配置来避免配置文件问题
        $this->config = [
            'app' => [
                'name' => 'CDN域名管理系统',
                'version' => '1.0.0',
            ],
            'security' => [
                'session_key_prefix' => 'cdn_',
                'csrf_token_name' => 'csrf_token',
            ]
        ];

        $this->cdnService = null;
    }

    /**
     * 显示主页 - API认证表单或域名列表
     */
    public function index()
    {
        // 检查是否已认证
        if (!$this->isAuthenticated()) {
            $this->showAuthForm();
            return;
        }

        // 显示域名列表
        $this->showDomainList();
    }

    /**
     * 显示API认证表单
     */
    private function showAuthForm()
    {
        $this->render('auth/index', [
            'title' => 'CDN域名管理系统 - 登录',
            'error' => $_SESSION['auth_error'] ?? null
        ]);

        // 清除错误信息
        unset($_SESSION['auth_error']);
    }

    /**
     * 显示域名列表
     */
    private function showDomainList()
    {
        $domains = [];
        $error = null;

        try {
            $cdnService = $this->initCdnService();
            $result = $cdnService->describeCdnDomains();
            if ($result['success']) {
                $domains = $result['data'];

                // 获取每个域名的详细信息（包括源站）
                foreach ($domains as &$domain) {
                    $detailResult = $cdnService->describeDomainDetail($domain['domain_name']);
                    if ($detailResult['success']) {
                        $domain['sources'] = $detailResult['data']['sources'] ?? [];
                        $domain['source_text'] = $this->formatSources($domain['sources']);
                    } else {
                        $domain['sources'] = [];
                        $domain['source_text'] = '获取失败';
                    }
                }
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = '获取域名列表失败: ' . $e->getMessage();
        }

        $this->render('domains/index', [
            'title' => 'CDN域名列表',
            'domains' => $domains,
            'error' => $error,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /**
     * 格式化源站信息显示
     */
    private function formatSources($sources)
    {
        if (empty($sources)) {
            return '未设置';
        }

        $formatted = [];
        foreach ($sources as $source) {
            $content = $source['content'];
            $type = $source['type'] === 'ipaddr' ? 'IP' : '域名';
            $port = $source['port'] != 80 ? ':' . $source['port'] : '';
            $formatted[] = "$content ($type$port)";
        }

        return implode(', ', $formatted);
    }

    /**
     * 处理API认证
     */
    public function authenticate()
    {
        $accessKeyId = trim($_POST['access_key_id'] ?? '');
        $accessSecret = trim($_POST['access_secret'] ?? '');

        if (empty($accessKeyId) || empty($accessSecret)) {
            $_SESSION['auth_error'] = '请输入Access Key ID和Access Key Secret';
            $this->redirect('?action=index');
            return;
        }

        try {
            // 测试API连接
            $testService = new CdnService($accessKeyId, $accessSecret);
            $testResult = $testService->describeCdnDomains();

            if ($testResult['success']) {
                // 保存到Session
                $sessionKeyPrefix = $this->config['security']['session_key_prefix'];
                $_SESSION[$sessionKeyPrefix . 'access_key_id'] = $accessKeyId;
                $_SESSION[$sessionKeyPrefix . 'access_secret'] = $accessSecret;
                $_SESSION[$sessionKeyPrefix . 'authenticated'] = true;

                $this->redirect('?action=index');
            } else {
                $_SESSION['auth_error'] = 'API认证失败: ' . $testResult['message'];
                $this->redirect('?action=index');
            }
        } catch (Exception $e) {
            $_SESSION['auth_error'] = 'API认证失败: ' . $e->getMessage();
            $this->redirect('?action=index');
        }
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        // 清除Session中的认证信息
        $sessionKeyPrefix = $this->config['security']['session_key_prefix'];
        unset($_SESSION[$sessionKeyPrefix . 'access_key_id']);
        unset($_SESSION[$sessionKeyPrefix . 'access_secret']);
        unset($_SESSION[$sessionKeyPrefix . 'authenticated']);

        $this->redirect('?action=index');
    }

    /**
     * 检查是否已认证
     */
    private function isAuthenticated()
    {
        $sessionKeyPrefix = $this->config['security']['session_key_prefix'];
        return $_SESSION[$sessionKeyPrefix . 'authenticated'] ?? false;
    }

    /**
     * 初始化CDN服务（需要Session已启动）
     */
    private function initCdnService()
    {
        if ($this->cdnService === null) {
            // 从Session获取API密钥
            $sessionKeyPrefix = $this->config['security']['session_key_prefix'];
            $accessKeyId = $_SESSION[$sessionKeyPrefix . 'access_key_id'] ?? null;
            $accessSecret = $_SESSION[$sessionKeyPrefix . 'access_secret'] ?? null;

            $this->cdnService = new CdnService($accessKeyId, $accessSecret);
        }
        return $this->cdnService;
    }

    /**
     * 生成CSRF token
     */
    private function generateCsrfToken()
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION[$this->config['security']['csrf_token_name']] = $token;
        return $token;
    }

    /**
     * 渲染视图
     */
    private function render($template, $data = [])
    {
        extract($data);
        $templatePath = __DIR__ . "/../views/{$template}.php";

        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            echo "模板文件不存在: {$template}";
        }
    }

    /**
     * 重定向
     */
    private function redirect($url)
    {
        header("Location: $url");
        exit;
    }
}