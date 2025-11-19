<?php

namespace App\Controllers;

use App\Services\CdnService;
use Exception;

class DomainController
{
    private $cdnService;
    private $config;

    public function __construct()
    {
        // 安全加载配置文件
        $configFile = __DIR__ . '/../../config/config.php';
        if (!file_exists($configFile)) {
            throw new Exception("配置文件不存在: {$configFile}");
        }

        $config = require $configFile;
        if (!is_array($config)) {
            throw new Exception("配置文件格式错误，期望数组但得到: " . gettype($config) . " (文件路径: {$configFile})");
        }

        // 验证必需的配置项
        $requiredKeys = ['app', 'security'];
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                throw new Exception("配置文件缺少必需的键: {$key}");
            }
        }

        if (!isset($config['security']['session_key_prefix'])) {
            throw new Exception("配置文件缺少 session_key_prefix 设置");
        }

        $this->config = $config;
        $this->cdnService = null;
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

                // 为每个域名格式化源站信息显示
                foreach ($domains as &$domain) {
                    // 直接使用从主API获取的源站信息
                    $domain['source_text'] = $this->formatSources($domain['sources']);
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

        // 检查是否是阿里云API的新格式
        if (isset($sources['Source'])) {
            $sourceList = $sources['Source'];
            if (!is_array($sourceList)) {
                $sourceList = [$sourceList];
            }

            $formatted = [];
            foreach ($sourceList as $source) {
                $formatted[] = $source;
            }
            return implode(', ', $formatted);
        }

        // 兼容旧格式
        $formatted = [];
        foreach ($sources as $source) {
            $content = $source['content'] ?? $source;
            $type = ($source['type'] ?? 'ipaddr') === 'ipaddr' ? 'IP' : '域名';
            $port = ($source['port'] ?? 80) != 80 ? ':' . ($source['port'] ?? 80) : '';
            $formatted[] = "$content ($type$port)";
        }

        return implode(', ', $formatted);
    }

    /**
     * 刷新域名列表
     */
    public function refresh()
    {
        if (!$this->isAuthenticated()) {
            $this->redirect('?action=index');
            return;
        }

        header('Content-Type: application/json');

        try {
            $cdnService = $this->initCdnService();
            $result = $cdnService->describeCdnDomains();
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => '刷新失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 批量更新源站
     */
    public function batchUpdate()
    {
        // 清除任何之前的输出
        if (ob_get_length()) ob_clean();

        // 设置JSON响应头
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => '未登录']);
            exit;
        }

        // 验证CSRF token
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF token验证失败']);
            exit;
        }

        // 处理domains参数（可能是JSON字符串）
        $domainsParam = $_POST['domains'] ?? [];
        if (is_string($domainsParam)) {
            $domains = json_decode($domainsParam, true) ?? [];
        } else {
            $domains = $domainsParam;
        }

        $newSource = trim($_POST['new_source'] ?? '');

        if (empty($domains)) {
            echo json_encode(['success' => false, 'message' => '请选择要更新的域名']);
            exit;
        }

        if (empty($newSource)) {
            echo json_encode(['success' => false, 'message' => '请输入新的源站地址']);
            exit;
        }

        // 验证源站
        $cdnService = $this->initCdnService();
        $sourceValidation = $cdnService->validateSource($newSource);
        if (!$sourceValidation['valid']) {
            echo json_encode(['success' => false, 'message' => $sourceValidation['message']]);
            exit;
        }

        try {
            $sourceData = [
                'content' => $newSource,
                'type' => $sourceValidation['type'] === 'ip' ? 'ipaddr' : ($sourceValidation['type'] === 'domain' ? 'domain' : 'ipaddr'),
                'port' => 80,
                'priority' => 20
            ];

            $results = [];
            $successCount = 0;
            $failCount = 0;

            foreach ($domains as $domain) {
                $result = $cdnService->updateDomainSources($domain, [$sourceData]);

                $results[] = [
                    'domain' => $domain,
                    'success' => $result['success'],
                    'message' => $result['message']
                ];

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }

                // 添加延迟避免API调用频率限制
                usleep(200000); // 0.2秒
            }

            $response = [
                'success' => $failCount === 0,
                'message' => "更新完成：成功 $successCount 个，失败 $failCount 个",
                'results' => $results
            ];

            echo json_encode($response);

        } catch (Exception $e) {
            error_log("批量更新异常: " . $e->getMessage());
            $errorMessage = $e->getMessage();

            if (strpos($errorMessage, 'InvalidAction.NotFound') !== false) {
                echo json_encode([
                    'success' => false,
                    'message' => '自动更新功能暂时不可用，请使用手动方式更新源站配置',
                    'manual_update' => true,
                    'guide_url' => 'manual_update_guide.php'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '批量更新失败: ' . $errorMessage]);
            }
        }

        exit;
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
     * 生成CSRF token
     */
    private function generateCsrfToken()
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION[$this->config['security']['csrf_token_name']] = $token;
        return $token;
    }

    /**
     * 验证CSRF token
     */
    private function validateCsrfToken($token)
    {
        return hash_equals($_SESSION[$this->config['security']['csrf_token_name']] ?? '', $token);
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

    /**
     * JSON响应
     */
    private function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}