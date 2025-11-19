<?php

namespace App\Services;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Cdn\Cdn;
use Exception;

class CdnService
{
    private $accessKeyId;
    private $accessSecret;

    public function __construct($accessKeyId = null, $accessSecret = null)
    {
        $this->accessKeyId = $accessKeyId;
        $this->accessSecret = $accessSecret;

        if ($accessKeyId && $accessSecret) {
            $this->initializeClient();
        }
    }

    /**
     * 初始化阿里云客户端
     */
    private function initializeClient()
    {
        try {
            AlibabaCloud::accessKeyClient($this->accessKeyId, $this->accessSecret)
                ->regionId('cn-hangzhou')
                ->name('cdn');
        } catch (Exception $e) {
            throw new Exception('初始化阿里云客户端失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取CDN域名列表
     * @return array
     */
    public function describeCdnDomains()
    {
        try {
            $result = AlibabaCloud::rpc()
                ->client('cdn')  // 使用已命名的客户端
                ->product('Cdn')
                ->version('2014-11-11')  // 使用较新的API版本
                ->action('DescribeUserDomains')
                ->method('POST')
                ->host('cdn.aliyuncs.com')
                ->options([
                    'query' => [
                        'PageSize' => 500,  // 增加页面大小
                        'PageNumber' => 1,
                    ],
                ])
                ->request();

            $domains = [];

            // 只记录关键错误日志

            // 根据实际数据结构解析
            if (isset($result['Domains']) && is_array($result['Domains'])) {

                $domainList = [];

                // 检查是否是PageData结构
                if (isset($result['Domains']['PageData']) && is_array($result['Domains']['PageData'])) {
                    $domainList = $result['Domains']['PageData'];
                }
                // 检查是否是Domain结构（兼容性）
                elseif (isset($result['Domains']['Domain']) && is_array($result['Domains']['Domain'])) {
                    $domainList = $result['Domains']['Domain'];
                }
                // 检查是否直接是域名数组
                else {
                    $domainList = $result['Domains'];
                }

                // 确保domainList是数组
                if (!is_array($domainList)) {
                    $domainList = [$domainList];
                }

                foreach ($domainList as $index => $domain) {

                    // 映射API字段到系统字段
                    $domains[] = [
                        'domain_name' => $domain['DomainName'] ?? $domain['domainName'] ?? '',
                        'cname' => $domain['Cname'] ?? $domain['cname'] ?? '',
                        'status' => $domain['DomainStatus'] ?? $domain['status'] ?? '',
                        'service_type' => $domain['CdnType'] ?? $domain['serviceType'] ?? 'web',
                        'update_time' => $domain['GmtModified'] ?? $domain['updateTime'] ?? '',
                        'create_time' => $domain['GmtCreated'] ?? $domain['createTime'] ?? '',

                        // 额外字段
                        'source_type' => $domain['SourceType'] ?? '',
                        'cdn_type' => $domain['CdnType'] ?? '',
                        'description' => $domain['Description'] ?? '',
                        'resource_group_id' => $domain['ResourceGroupId'] ?? '',
                        'ssl_protocol' => $domain['SslProtocol'] ?? '',
                        'sandbox' => $domain['Sandbox'] ?? '',

                        // 源站信息
                        'sources' => $domain['Sources'] ?? [],
                    ];
                }

                // 从响应中获取总数
                $totalCount = $result['TotalCount'] ?? count($domains);
                error_log("解析完成，总域名数: $totalCount");

                return [
                    'success' => true,
                    'data' => $domains,
                    'total' => $totalCount,
                    'raw_response' => $result
                ];

            } else {
                return [
                    'success' => false,
                    'message' => 'API响应格式异常：未找到Domains数据',
                    'raw_response' => $result
                ];
            }

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            // 针对不同错误类型提供更友好的提示
            if (strpos($errorMessage, 'InvalidAccessKeyId.NotFound') !== false) {
                return [
                    'success' => false,
                    'message' => 'Access Key ID不存在，请检查：1) Access Key ID是否正确输入 2) 是否已启用该Access Key 3) 是否在正确的阿里云账户下创建'
                ];
            } elseif (strpos($errorMessage, 'SignatureDoesNotMatch') !== false) {
                return [
                    'success' => false,
                    'message' => 'Access Key Secret错误，请检查密钥是否正确'
                ];
            } elseif (strpos($errorMessage, 'Forbidden.RAM') !== false) {
                return [
                    'success' => false,
                    'message' => '权限不足：AccessKey没有CDN访问权限，请在RAM控制台添加AliyunCDNReadOnlyAccess权限'
                ];
            } elseif (strpos($errorMessage, 'Forbidden') !== false) {
                return [
                    'success' => false,
                    'message' => '账户可能欠费或CDN服务未开通，请检查阿里云账户状态'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '获取CDN域名列表失败: ' . $errorMessage
                ];
            }
        }
    }

    /**
     * 获取域名详细信息
     * @param string $domainName
     * @return array
     */
    public function describeDomainDetail($domainName)
    {
        try {
            $result = AlibabaCloud::rpc()
                ->client('cdn')  // 使用已命名的客户端
                ->product('Cdn')
                ->version('2014-11-11')  // 使用较新的API版本
                ->action('DescribeDomainDetail')
                ->method('POST')
                ->host('cdn.aliyuncs.com')
                ->options([
                    'query' => [
                        'DomainName' => $domainName,
                    ],
                ])
                ->request();

            $detail = [];
            $sources = [];

            // 尝试多种可能的数据结构
            if (isset($result['DomainDetail'])) {
                $detail = $result['DomainDetail'];
            } elseif (isset($result['DomainConfigs'])) {
                $detail = $result['DomainConfigs'];
            } else {
                // 如果以上都没有，直接使用整个结果
                $detail = $result;
            }

            // 提取源站信息 - 兼容多种格式
            if (isset($detail['Sources']['Source'])) {
                $sourceList = $detail['Sources']['Source'];
                if (!is_array($sourceList)) {
                    $sourceList = [$sourceList];
                }
                foreach ($sourceList as $source) {
                    $sources[] = [
                        'content' => $source['Content'] ?? $source,
                        'type' => $source['Type'] ?? 'ipaddr',
                        'port' => $source['Port'] ?? 80,
                        'priority' => $source['Priority'] ?? 20,
                    ];
                }
            } elseif (isset($detail['Sources']) && is_array($detail['Sources'])) {
                // 如果Sources本身就是源站数组
                foreach ($detail['Sources'] as $source) {
                    $sources[] = [
                        'content' => $source,
                        'type' => 'domain',
                        'port' => 80,
                        'priority' => 20,
                    ];
                }
            }

            $detail['sources'] = $sources;

            return [
                'success' => true,
                'data' => $detail
            ];

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            // 提供更友好的错误信息
            if (strpos($errorMessage, 'InvalidAccessKeyId.NotFound') !== false) {
                return [
                    'success' => false,
                    'message' => '认证失败：Access Key ID不存在'
                ];
            } elseif (strpos($errorMessage, 'Forbidden') !== false) {
                return [
                    'success' => false,
                    'message' => '权限不足：AccessKey没有CDN访问权限'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '获取域名详细信息失败: ' . $errorMessage
                ];
            }
        }
    }

    /**
     * 更新域名源站
     * @param string $domainName
     * @param array $sources
     * @return array
     */
    public function updateDomainSources($domainName, $sources)
    {
        try {
            $sourcesParam = [];
            foreach ($sources as $source) {
                $sourcesParam[] = [
                    'content' => $source['content'],
                    'type' => $source['type'],
                    'port' => $source['port'] ?? 80,
                    'priority' => $source['priority'] ?? 20,
                ];
            }

            // 构建源站参数 - 添加更多验证和格式化
            $originList = [];
            foreach ($sources as $source) {
                $sourceContent = trim($source['content']);

                // 基本验证
                if (empty($sourceContent)) {
                    continue; // 跳过空的源站
                }

                // 根据测试成功的格式构建源站对象
                $originList[] = [
                    'content' => $sourceContent,
                    'type' => $source['type'] === 'ipaddr' ? 'ipaddr' : 'domain',
                    'port' => (int)($source['port'] ?? 80),
                    'priority' => 20
                ];
            }

            // 如果没有有效的源站，返回错误
            if (empty($originList)) {
                return [
                    'success' => false,
                    'message' => '没有有效的源站配置'
                ];
            }

            // 记录调试信息
            error_log("即将调用ModifyCdnDomain API，源站列表: " . json_encode($originList, JSON_UNESCAPED_UNICODE));

            // 根据测试结果使用正确的源站参数格式
            $result = AlibabaCloud::rpc()
                ->client('cdn')
                ->product('Cdn')
                ->version('2018-05-10')  // 使用2018-05-10版本
                ->action('ModifyCdnDomain')
                ->method('POST')
                ->host('cdn.aliyuncs.com')
                ->options([
                    'query' => [
                        'DomainName' => $domainName,
                        'Sources' => json_encode($originList)  // 使用完整的对象格式
                    ],
                ])
                ->request();

            return [
                'success' => true,
                'message' => '域名源站更新成功'
            ];

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            // 提供更友好的错误信息
            if (strpos($errorMessage, 'InvalidAction.NotFound') !== false) {
                return [
                    'success' => false,
                    'message' => 'API不支持：当前阿里云API版本不支持此操作，请手动在阿里云控制台更新源站'
                ];
            } elseif (strpos($errorMessage, 'Forbidden') !== false) {
                return [
                    'success' => false,
                    'message' => '权限不足：AccessKey没有CDN写入权限，请在阿里云RAM控制台添加权限'
                ];
            } elseif (strpos($errorMessage, 'InvalidParameter') !== false) {
                return [
                    'success' => false,
                    'message' => '参数错误：源站配置参数格式不正确'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '更新失败: ' . $errorMessage
                ];
            }
        }
    }

    /**
     * 验证源站
     * @param string $source
     * @return array
     */
    public function validateSource($source)
    {
        // 检查是否为IP地址
        if (filter_var($source, FILTER_VALIDATE_IP)) {
            return [
                'valid' => true,
                'type' => 'ip',
                'message' => '有效的IP地址'
            ];
        }

        // 检查是否为域名
        if (filter_var($source, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return [
                'valid' => true,
                'type' => 'domain',
                'message' => '有效的域名'
            ];
        }

        return [
            'valid' => false,
            'type' => 'invalid',
            'message' => '无效的源站地址'
        ];
    }

    /**
     * 批量更新源站
     * @param array $domains
     * @param array $newSource
     * @return array
     */
    public function batchUpdateSources($domains, $newSource)
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($domains as $domain) {
            $result = $this->updateDomainSources($domain, [$newSource]);
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
            usleep(100000); // 0.1秒
        }

        return [
            'success' => $failCount === 0,
            'total' => count($domains),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'results' => $results
        ];
    }
}