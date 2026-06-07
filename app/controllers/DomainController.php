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
     * 批量设置用量封顶
     */
    public function batchSetUsageCap()
    {
        if (ob_get_length()) ob_clean();

        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => '未登录']);
            exit;
        }

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF token验证失败']);
            exit;
        }

        $domainsParam = $_POST['domains'] ?? [];
        if (is_string($domainsParam)) {
            $domains = json_decode($domainsParam, true) ?? [];
        } else {
            $domains = $domainsParam;
        }

        $capType = trim($_POST['cap_type'] ?? '');
        $period = trim($_POST['period'] ?? '');
        $threshold = trim($_POST['threshold'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $unblockTime = trim($_POST['unblock_time'] ?? '');

        $validation = $this->validateUsageCapParams($domains, $capType, $period, $threshold, $unit, $unblockTime);
        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'message' => $validation['message']]);
            exit;
        }

        try {
            $cdnService = $this->initCdnService();
            $results = [];
            $successCount = 0;
            $failCount = 0;
            $label = $this->getUsageCapLabel($capType);

            foreach ($domains as $domain) {
                if ($capType === 'traffic') {
                    $result = $cdnService->setTrafficCap($domain, $period, (float)$threshold, $unit, $unblockTime);
                } elseif ($capType === 'bandwidth') {
                    $result = $cdnService->setBandwidthCap($domain, (float)$threshold, $unit, $unblockTime);
                } else {
                    $result = $cdnService->setHttpsRequestCap($domain, $period, (float)$threshold, $unit, $unblockTime);
                }

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

                usleep(200000);
            }

            echo json_encode([
                'success' => $failCount === 0,
                'message' => "{$label}设置完成：成功 {$successCount} 个，失败 {$failCount} 个",
                'results' => $results
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("批量设置用量封顶异常: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '批量设置用量封顶失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /**
     * 验证用量封顶参数
     */
    private function validateUsageCapParams($domains, $capType, $period, $threshold, $unit, $unblockTime)
    {
        if (empty($domains) || !is_array($domains)) {
            return ['valid' => false, 'message' => '请选择要设置的域名'];
        }

        $allowedTypes = ['traffic', 'bandwidth', 'https_request'];
        if (!in_array($capType, $allowedTypes, true)) {
            return ['valid' => false, 'message' => '请选择有效的封顶类型'];
        }

        if ($threshold === '' || !is_numeric($threshold) || (float)$threshold <= 0) {
            return ['valid' => false, 'message' => '请输入有效的封顶阈值'];
        }

        $allowedUnblockTimes = ['5m', '1h', '1d', '1month'];
        if (!in_array($unblockTime, $allowedUnblockTimes, true)) {
            return ['valid' => false, 'message' => '请选择有效的解封时间'];
        }

        if ($capType !== 'bandwidth') {
            $allowedPeriods = ['5m', '1h'];
            if (!in_array($period, $allowedPeriods, true)) {
                return ['valid' => false, 'message' => '请选择有效的统计周期'];
            }
        }

        $value = (float)$threshold;
        if ($capType === 'traffic') {
            return $this->validateUsageCapRange($value, $unit, ['MB' => 1, 'GB' => 1024, 'TB' => 1024 * 1024], 1, 10000 * 1024 * 1024, '流量封顶阈值范围为1 MB ~ 10000 TB');
        }

        if ($capType === 'bandwidth') {
            return $this->validateUsageCapRange($value, $unit, ['Mbps' => 1, 'Gbps' => 1000, 'Tbps' => 1000 * 1000], 1, 1000 * 1000, '带宽封顶阈值范围为1 Mbps ~ 1 Tbps');
        }

        return $this->validateUsageCapRange($value, $unit, ['million' => 1, 'billion' => 1000], 1, 10000, 'HTTPS请求数封顶阈值范围为100万次 ~ 100亿次');
    }

    /**
     * 验证用量封顶阈值范围
     */
    private function validateUsageCapRange($value, $unit, $unitMultipliers, $minBaseValue, $maxBaseValue, $message)
    {
        if (!isset($unitMultipliers[$unit])) {
            return ['valid' => false, 'message' => '请选择有效的阈值单位'];
        }

        $baseValue = $value * $unitMultipliers[$unit];
        if ($baseValue < $minBaseValue || $baseValue > $maxBaseValue) {
            return ['valid' => false, 'message' => $message];
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * 获取用量封顶类型名称
     */
    private function getUsageCapLabel($capType)
    {
        $labels = [
            'traffic' => '流量封顶',
            'bandwidth' => '带宽封顶',
            'https_request' => 'HTTPS请求数封顶',
        ];

        return $labels[$capType] ?? '用量封顶';
    }

    /**
     * 批量设置访问控制
     */
    public function batchSetAccessControl()
    {
        if (ob_get_length()) ob_clean();

        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => '未登录']);
            exit;
        }

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF token验证失败']);
            exit;
        }

        $domainsParam = $_POST['domains'] ?? [];
        if (is_string($domainsParam)) {
            $domains = json_decode($domainsParam, true) ?? [];
        } else {
            $domains = $domainsParam;
        }

        $accessType = trim($_POST['access_type'] ?? '');
        $rules = trim($_POST['rules'] ?? '');
        $validation = $this->validateAccessControlParams($domains, $accessType, $rules);
        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'message' => $validation['message']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $cdnService = $this->initCdnService();
            $results = [];
            $successCount = 0;
            $failCount = 0;
            $label = $this->getAccessControlLabel($accessType);

            foreach ($domains as $domain) {
                $result = $cdnService->setAccessControl($domain, $accessType, $validation['rules']);
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

                usleep(200000);
            }

            echo json_encode([
                'success' => $failCount === 0,
                'message' => "{$label}设置完成：成功 {$successCount} 个，失败 {$failCount} 个",
                'results' => $results
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("批量设置访问控制异常: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '批量设置访问控制失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /**
     * 验证访问控制参数
     */
    private function validateAccessControlParams($domains, $accessType, $rules)
    {
        if (empty($domains) || !is_array($domains)) {
            return ['valid' => false, 'message' => '请选择要设置的域名'];
        }

        $allowedTypes = ['ip_black', 'ip_white', 'ua_black', 'ua_white'];
        if (!in_array($accessType, $allowedTypes, true)) {
            return ['valid' => false, 'message' => '请选择有效的访问控制类型'];
        }

        if (strpos($accessType, 'ip_') === 0) {
            $items = $this->splitAccessControlRules($rules, '/[\r\n,]+/');
            if (empty($items)) {
                return ['valid' => false, 'message' => '请输入IP地址或CIDR地址段'];
            }

            foreach ($items as $item) {
                if (!$this->isValidIpOrCidr($item)) {
                    return ['valid' => false, 'message' => "无效的IP地址或CIDR地址段: {$item}"];
                }
            }

            return ['valid' => true, 'rules' => implode(',', $items)];
        }

        $items = $this->splitAccessControlRules($rules, '/[\r\n|]+/');
        if (empty($items)) {
            return ['valid' => false, 'message' => '请输入UA规则'];
        }

        return ['valid' => true, 'rules' => implode('|', $items)];
    }

    /**
     * 拆分并清理访问控制规则
     */
    private function splitAccessControlRules($rules, $pattern)
    {
        $items = preg_split($pattern, $rules);
        $items = array_map('trim', $items ?: []);
        $items = array_filter($items, function ($item) {
            return $item !== '';
        });

        return array_values(array_unique($items));
    }

    /**
     * 验证IP或CIDR
     */
    private function isValidIpOrCidr($value)
    {
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (strpos($value, '/') === false) {
            return false;
        }

        [$ip, $prefix] = explode('/', $value, 2);
        if (!filter_var($ip, FILTER_VALIDATE_IP) || $prefix === '' || !ctype_digit($prefix)) {
            return false;
        }

        $prefixLength = (int)$prefix;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $prefixLength >= 0 && $prefixLength <= 32 && $value !== '0.0.0.0/0';
        }

        return $prefixLength >= 0 && $prefixLength <= 128 && $value !== '::/0';
    }

    /**
     * 获取访问控制类型名称
     */
    private function getAccessControlLabel($accessType)
    {
        $labels = [
            'ip_black' => 'IP黑名单',
            'ip_white' => 'IP白名单',
            'ua_black' => 'UA黑名单',
            'ua_white' => 'UA白名单',
        ];

        return $labels[$accessType] ?? '访问控制';
    }

    /**
     * 批量设置IPv6开关
     */
    public function batchSetIpv6()
    {
        if (ob_get_length()) ob_clean();

        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => '未登录']);
            exit;
        }

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF token验证失败']);
            exit;
        }

        $domainsParam = $_POST['domains'] ?? [];
        if (is_string($domainsParam)) {
            $domains = json_decode($domainsParam, true) ?? [];
        } else {
            $domains = $domainsParam;
        }

        $status = trim($_POST['ipv6_status'] ?? '');
        $validation = $this->validateIpv6Params($domains, $status);
        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'message' => $validation['message']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $cdnService = $this->initCdnService();
            $results = [];
            $successCount = 0;
            $failCount = 0;
            $label = $this->getIpv6Label($status);

            foreach ($domains as $domain) {
                $result = $cdnService->setIpv6($domain, $status);
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

                usleep(200000);
            }

            echo json_encode([
                'success' => $failCount === 0,
                'message' => "{$label}完成：成功 {$successCount} 个，失败 {$failCount} 个",
                'results' => $results
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("批量设置IPv6开关异常: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '批量设置IPv6开关失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /**
     * 验证IPv6开关参数
     */
    private function validateIpv6Params($domains, $status)
    {
        if (empty($domains) || !is_array($domains)) {
            return ['valid' => false, 'message' => '请选择要设置的域名'];
        }

        if (!in_array($status, ['on', 'off'], true)) {
            return ['valid' => false, 'message' => '请选择有效的IPv6开关状态'];
        }

        return ['valid' => true];
    }

    /**
     * 获取IPv6开关操作名称
     */
    private function getIpv6Label($status)
    {
        return $status === 'on' ? '开启IPv6' : '关闭IPv6';
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
