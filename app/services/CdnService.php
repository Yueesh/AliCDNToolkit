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
     * 设置流量封顶
     * @param string $domainName
     * @param string $period
     * @param float $threshold
     * @param string $unit
     * @param string $unblockTime
     * @return array
     */
    public function setTrafficCap($domainName, $period, $threshold, $unit, $unblockTime)
    {
        return $this->setCappingRule($domainName, 'Traffic', 'Traffic', $this->mapCappingPeriod($period), $this->convertUsageCapValue($threshold, $unit, [
            'MB' => 1,
            'GB' => 1024,
            'TB' => 1024 * 1024,
        ]), $this->mapCappingRecover($unblockTime), '流量封顶');
    }

    /**
     * 设置带宽封顶
     * @param string $domainName
     * @param float $threshold
     * @param string $unit
     * @param string $unblockTime
     * @return array
     */
    public function setBandwidthCap($domainName, $threshold, $unit, $unblockTime)
    {
        return $this->setCappingRule($domainName, 'Bandwidth', 'Bandwidth', 'MIN5', $this->convertUsageCapValue($threshold, $unit, [
            'Mbps' => 1,
            'Gbps' => 1000,
            'Tbps' => 1000 * 1000,
        ]), $this->mapCappingRecover($unblockTime), '带宽封顶');
    }

    /**
     * 设置HTTPS请求数封顶
     * @param string $domainName
     * @param string $period
     * @param float $threshold
     * @param string $unit
     * @param string $unblockTime
     * @return array
     */
    public function setHttpsRequestCap($domainName, $period, $threshold, $unit, $unblockTime)
    {
        return $this->setCappingRule($domainName, 'RequestHTTPS', 'RequestHTTPS', $this->mapCappingPeriod($period), $this->convertUsageCapValue($threshold, $unit, [
            'million' => 1,
            'billion' => 1000,
        ]), $this->mapCappingRecover($unblockTime), 'HTTPS请求数封顶');
    }

    /**
     * 设置用量封顶规则
     * @param string $domainName
     * @param string $name
     * @param string $metric
     * @param string $period
     * @param int $value
     * @param string $recover
     * @param string $label
     * @return array
     */
    private function setCappingRule($domainName, $name, $metric, $period, $value, $recover, $label)
    {
        try {
            $params = [
                'DomainName' => $domainName,
                'CreateOnly' => false,
                'Name' => $name,
                'SimpleDescription' => [
                    'Elements' => [[
                        'Metric' => $metric,
                        'Value' => $value,
                        'Dimension' => 'Domain',
                        'DimensionValue' => $domainName,
                        'Comparison' => 'gt',
                    ]],
                ],
                'Period' => $period,
                'CappingAction' => [
                    'Type' => 'DisableDomain',
                    'Scope' => [$domainName],
                    'Recover' => $recover,
                ],
            ];

            AlibabaCloud::rpc()
                ->client('cdn')
                ->product('Cdn')
                ->version('2018-05-10')
                ->action('SetCappingRule')
                ->method('POST')
                ->scheme('https')
                ->host('cdn.aliyuncs.com')
                ->options([
                    'query' => [
                        'DomainName' => $domainName,
                        'CreateOnly' => false,
                        'Name' => $name,
                        'SimpleDescription' => json_encode($params['SimpleDescription'], JSON_UNESCAPED_UNICODE),
                        'Period' => $period,
                        'CappingAction' => json_encode($params['CappingAction'], JSON_UNESCAPED_UNICODE),
                    ],
                ])
                ->request();

            return [
                'success' => true,
                'message' => $label . '设置成功'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $this->formatUsageCapError($e->getMessage())
            ];
        }
    }

    /**
     * 格式化用量封顶错误信息
     * @param string $errorMessage
     * @return string
     */
    private function formatUsageCapError($errorMessage)
    {
        if (strpos($errorMessage, 'Forbidden') !== false) {
            return '权限不足：AccessKey没有CDN写入权限，请在阿里云RAM控制台添加相应权限';
        }

        if (strpos($errorMessage, 'InvalidProtocol.NeedSsl') !== false) {
            return '阿里云要求该接口必须使用HTTPS，请确认当前阿里云SDK支持HTTPS scheme设置。阿里云原始错误: ' . $errorMessage;
        }

        if (strpos($errorMessage, 'InvalidAction.NotFound') !== false || strpos($errorMessage, 'InvalidAction') !== false) {
            return '当前API方式不可用：SetCappingRule可能仅开放给阿里云控制台网关，请在CDN控制台的“流量限制 > 用量封顶”中设置。阿里云原始错误: ' . $errorMessage;
        }

        if (strpos($errorMessage, 'InvalidParameter') !== false || strpos($errorMessage, 'MissingParameter') !== false) {
            return '参数错误：用量封顶配置参数格式不正确';
        }

        if (strpos($errorMessage, 'InvalidDomain') !== false) {
            return '域名无效或不属于当前账号';
        }

        return '设置失败: ' . $errorMessage;
    }

    /**
     * 转换用量封顶阈值为阿里云规则内部单位
     * @param float|int|string $number
     * @param string $unit
     * @param array $unitMultipliers
     * @return int
     */
    private function convertUsageCapValue($number, $unit, $unitMultipliers)
    {
        return (int)round((float)$number * $unitMultipliers[$unit]);
    }

    /**
     * 映射统计周期到阿里云封顶规则枚举
     * @param string $period
     * @return string
     */
    private function mapCappingPeriod($period)
    {
        $periods = [
            '5m' => 'MIN5',
            '1h' => 'HOUR',
        ];

        return $periods[$period];
    }

    /**
     * 映射解封时间到阿里云封顶规则枚举
     * @param string $unblockTime
     * @return string
     */
    private function mapCappingRecover($unblockTime)
    {
        $recoverTimes = [
            '5m' => 'MIN5',
            '1h' => 'HOUR',
            '1d' => 'DAY',
            '1month' => 'MONTH',
        ];

        return $recoverTimes[$unblockTime];
    }

    /**
     * 设置IP/UA黑白名单访问控制
     * @param string $domainName
     * @param string $accessType
     * @param string $rules
     * @return array
     */
    public function setAccessControl($domainName, $accessType, $rules)
    {
        $configs = [
            'ip_black' => [
                'function' => 'ip_black_list_set',
                'args' => ['ip_list' => $rules],
                'label' => 'IP黑名单',
            ],
            'ip_white' => [
                'function' => 'ip_allow_list_set',
                'args' => ['ip_list' => $rules],
                'label' => 'IP白名单',
            ],
            'ua_black' => [
                'function' => 'ali_ua',
                'args' => ['ua' => $rules, 'type' => 'black'],
                'label' => 'UA黑名单',
            ],
            'ua_white' => [
                'function' => 'ali_ua',
                'args' => ['ua' => $rules, 'type' => 'white'],
                'label' => 'UA白名单',
            ],
        ];

        $config = $configs[$accessType] ?? null;
        if ($config === null) {
            return [
                'success' => false,
                'message' => '无效的访问控制类型'
            ];
        }

        return $this->setDomainConfig($domainName, $config['function'], $config['args'], $config['label']);
    }

    /**
     * 设置IPv6访问开关
     * @param string $domainName
     * @param string $status
     * @return array
     */
    public function setIpv6($domainName, $status)
    {
        if (!in_array($status, ['on', 'off'], true)) {
            return [
                'success' => false,
                'message' => '无效的IPv6开关状态'
            ];
        }

        $label = $status === 'on' ? 'IPv6开启' : 'IPv6关闭';

        return $this->setDomainConfig($domainName, 'ipv6', [
            'switch' => $status,
            'region' => '*',
        ], $label);
    }

    /**
     * 设置CDN域名配置
     * @param string $domainName
     * @param string $functionName
     * @param array $args
     * @param string $label
     * @return array
     */
    private function setDomainConfig($domainName, $functionName, $args, $label)
    {
        try {
            $functionArgs = [];
            foreach ($args as $argName => $argValue) {
                $functionArgs[] = [
                    'argName' => $argName,
                    'argValue' => (string)$argValue,
                ];
            }

            $functions = [[
                'functionName' => $functionName,
                'functionArgs' => $functionArgs,
            ]];

            AlibabaCloud::rpc()
                ->client('cdn')
                ->product('Cdn')
                ->version('2018-05-10')
                ->action('BatchSetCdnDomainConfig')
                ->method('POST')
                ->scheme('https')
                ->host('cdn.aliyuncs.com')
                ->options([
                    'query' => [
                        'DomainNames' => $domainName,
                        'Functions' => json_encode($functions, JSON_UNESCAPED_UNICODE),
                    ],
                ])
                ->request();

            return [
                'success' => true,
                'message' => $label . '设置成功'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $this->formatDomainConfigError($e->getMessage(), $label)
            ];
        }
    }

    /**
     * 格式化CDN域名配置错误信息
     * @param string $errorMessage
     * @param string $label
     * @return string
     */
    private function formatDomainConfigError($errorMessage, $label)
    {
        if (strpos($errorMessage, 'Forbidden') !== false) {
            return '权限不足：AccessKey没有CDN写入权限，请在阿里云RAM控制台添加相应权限';
        }

        if (strpos($errorMessage, 'InvalidParameter') !== false || strpos($errorMessage, 'MissingParameter') !== false) {
            return '参数错误：' . $label . '配置参数格式不正确';
        }

        if (strpos($errorMessage, 'ConfigAlreadyExists') !== false || strpos($errorMessage, 'FunctionConflict') !== false || strpos($errorMessage, 'Conflict') !== false) {
            if (strpos($label, '黑名单') !== false || strpos($label, '白名单') !== false) {
                return '配置冲突：黑白名单互斥，请先在阿里云CDN控制台删除相反类型配置。阿里云原始错误: ' . $errorMessage;
            }

            return '配置冲突：当前域名已有冲突配置，请在阿里云CDN控制台确认后重试。阿里云原始错误: ' . $errorMessage;
        }

        return '设置失败: ' . $errorMessage;
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
