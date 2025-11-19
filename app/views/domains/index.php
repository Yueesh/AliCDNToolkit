<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border: none;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .source-info {
            max-width: 300px;
            word-wrap: break-word;
        }
        .loading {
            display: none;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .domain-checkbox {
            width: 18px;
            height: 18px;
        }
        .batch-actions {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 2px dashed #dee2e6;
            display: none;
        }
        .batch-actions.show {
            display: block;
        }
        .progress {
            height: 8px;
        }
        .alert {
            border-radius: 8px;
        }

        /* 搜索框样式 */
        .input-group-text {
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }

        /* 分页样式 */
        .page-link {
            color: #667eea;
            border-color: #dee2e6;
        }

        .page-link:hover {
            color: #764ba2;
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }

        /* 页面大小按钮样式 */
        .btn-group .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        /* 表格行悬停效果 */
        #domainsTableBody tr {
            transition: all 0.2s ease;
        }

        #domainsTableBody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* 无数据提示样式 */
        #noDataMessage {
            padding: 60px 20px;
        }

        /* 搜索结果统计样式 */
        #resultStats {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-dark navbar-expand-lg mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-server"></i>
                阿里云CDN域名管理系统
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="?action=logout" title="退出登录">
                    <i class="fas fa-sign-out-alt"></i>
                    退出
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- 错误提示 -->
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- 主卡片 -->
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <div class="row align-items-center mb-3">
                            <div class="col">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i>
                                    CDN域名列表
                                </h5>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-outline-primary" id="refreshBtn">
                                    <i class="fas fa-sync-alt"></i>
                                    <span class="btn-text">刷新列表</span>
                                </button>
                            </div>
                        </div>

                        <!-- 搜索区域 -->
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="searchDomain"
                                           placeholder="搜索域名...">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-server"></i>
                                    </span>
                                    <input type="text" class="form-control" id="searchSource"
                                           placeholder="搜索源站地址...">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-secondary w-100" id="clearSearch">
                                    <i class="fas fa-times"></i>
                                    清除搜索
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- 批量操作区域 -->
                        <div class="batch-actions" id="batchActions">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-edit"></i>
                                        新源站地址
                                    </label>
                                    <input type="text" class="form-control" id="newSource"
                                           placeholder="输入IP地址或域名">
                                    <small class="text-muted">支持IP地址和域名格式</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="button" class="btn btn-primary btn-lg" id="batchUpdateBtn">
                                            <i class="fas fa-save"></i>
                                            批量更新源站
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary" id="clearSelection">
                                            <i class="fas fa-times"></i>
                                            清除选择
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="progress mt-2" id="updateProgress" style="display: none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                     role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>

                        <!-- 结果统计 -->
                        <div class="row mb-3" id="resultStats" style="display: none;">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    显示 <span id="showingFrom">0</span> - <span id="showingTo">0</span> 条，共 <span id="totalResults">0</span> 条记录
                                </small>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary" data-page-size="10">10条/页</button>
                                    <button type="button" class="btn btn-outline-secondary" data-page-size="20">20条/页</button>
                                    <button type="button" class="btn btn-outline-secondary" data-page-size="50">50条/页</button>
                                </div>
                            </div>
                        </div>

                        <!-- 域名列表 -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="domainsTable">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" class="form-check-input domain-checkbox"
                                                   id="selectAll">
                                        </th>
                                        <th>域名</th>
                                        <th>状态</th>
                                        <th>源站</th>
                                        <th>类型</th>
                                        <th>更新时间</th>
                                    </tr>
                                </thead>
                                <tbody id="domainsTableBody">
                                    <!-- 动态生成的表格内容 -->
                                </tbody>
                            </table>
                        </div>

                        <!-- 分页控件 -->
                        <nav aria-label="域名列表分页" id="paginationContainer" style="display: none;">
                            <ul class="pagination justify-content-center" id="pagination">
                                <!-- 动态生成的分页按钮 -->
                            </ul>
                        </nav>

                        <!-- 无数据提示 -->
                        <div id="noDataMessage" style="display: none;">
                            <div class="text-center py-4">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">未找到匹配的域名</h5>
                                <p class="text-muted">请尝试调整搜索条件</p>
                            </div>
                        </div>

                        <!-- Loading状态 -->
                        <div class="loading text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">加载中...</span>
                            </div>
                            <p class="mt-2">正在获取CDN域名列表...</p>
                        </div>
                    </div>
                </div>

                <!-- 统计信息 -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card border-0 bg-white shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-server text-primary fa-2x mb-2"></i>
                                <h6 class="card-title">总域名数</h6>
                                <h3 class="text-primary" id="totalDomains"><?php echo count($domains); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-white shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                <h6 class="card-title">在线域名</h6>
                                <h3 class="text-success" id="onlineDomains"><?php
                                    echo count(array_filter($domains, fn($d) => $d['status'] === 'online'));
                                ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-white shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-times-circle text-danger fa-2x mb-2"></i>
                                <h6 class="card-title">离线域名</h6>
                                <h3 class="text-danger" id="offlineDomains"><?php
                                    echo count(array_filter($domains, fn($d) => $d['status'] === 'offline'));
                                ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-white shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-exchange-alt text-warning fa-2x mb-2"></i>
                                <h6 class="card-title">已选择</h6>
                                <h3 class="text-warning" id="selectedDomains">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 确认对话框 -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        确认批量更新
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭确认对话框"></button>
                </div>
                <div class="modal-body">
                    <p>您确定要将以下域名的源站更新为：</p>
                    <div class="alert alert-primary">
                        <strong id="confirmNewSource"></strong>
                    </div>
                    <div id="confirmDomains"></div>
                    <hr>
                    <p class="text-warning">
                        <i class="fas fa-info-circle"></i>
                        注意：源站修改可能影响线上服务，请确认操作正确！
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        取消
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmUpdate">
                        确认更新
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 结果对话框 -->
    <div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resultModalLabel">
                        <i class="fas fa-check-circle text-success"></i>
                        批量更新结果
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭批量更新结果"></button>
                </div>
                <div class="modal-body" id="resultContent">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="refreshAfterUpdate">
                        刷新列表
                    </button>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="csrfToken" value="<?php echo $csrf_token; ?>">

    <!-- 将域名数据传递给JavaScript -->
    <script>
        // 域名数据
        window.domainsData = <?php echo json_encode($domains, JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <script src="/js/bootstrap.bundle.min.js"></script>
    <script src="/js/all.min.js"></script>
    <script>
        // 全局变量
        let allDomains = window.domainsData || [];
        let filteredDomains = [];
        let selectedDomains = [];
        let currentPage = 1;
        let pageSize = 10;

        // DOM元素
        const selectAllCheckbox = document.getElementById('selectAll');
        const batchActions = document.getElementById('batchActions');
        const selectedCount = document.getElementById('selectedDomains');
        const refreshBtn = document.getElementById('refreshBtn');
        const batchUpdateBtn = document.getElementById('batchUpdateBtn');
        const clearSelectionBtn = document.getElementById('clearSelection');
        const newSourceInput = document.getElementById('newSource');
        const searchDomainInput = document.getElementById('searchDomain');
        const searchSourceInput = document.getElementById('searchSource');
        const clearSearchBtn = document.getElementById('clearSearch');

        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化分页数据
            filteredDomains = [...allDomains];
            renderTable();
            updateSelectedCount();

            // 修复所有关闭按钮的aria-hidden冲突问题
            const closeButtons = document.querySelectorAll('[data-bs-dismiss="modal"]');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // 移除焦点以避免aria-hidden冲突
                    this.blur();
                });
            });

            // 初始化页面大小按钮
            initPageSizeButtons();

            // 绑定全选事件
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.domain-select');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelectedCount();
            });
        });

        
        // 更新选择数量
        function updateSelectedCount() {
            selectedDomains = Array.from(document.querySelectorAll('.domain-select:checked'))
                .map(cb => cb.value);

            selectedCount.textContent = selectedDomains.length;

            // 显示或隐藏批量操作区域
            if (selectedDomains.length > 0) {
                batchActions.classList.add('show');
            } else {
                batchActions.classList.remove('show');
            }

            // 更新全选状态
            updateSelectAllState();
        }

        // 更新全选状态
        function updateSelectAllState() {
            const visibleCheckboxes = document.querySelectorAll('.domain-select');
            const selectedVisibleCount = Array.from(visibleCheckboxes).filter(cb => cb.checked).length;

            if (selectedVisibleCount === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (selectedVisibleCount === visibleCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
                selectAllCheckbox.checked = false;
            }
        }

        // 清除选择
        clearSelectionBtn.addEventListener('click', function() {
            selectAllCheckbox.checked = false;
            domainCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        });

        // 刷新列表
        refreshBtn.addEventListener('click', function() {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 刷新中...';

            fetch('?action=refresh', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 重新加载页面以显示最新数据
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('刷新失败: ' + data.message);
                }
            })
            .catch(error => {
                alert('刷新失败: ' + error.message);
            })
            .finally(() => {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> 刷新列表';
            });
        });

        // 批量更新
        batchUpdateBtn.addEventListener('click', function() {
            const newSource = newSourceInput.value.trim();

            if (!newSource) {
                alert('请输入新的源站地址');
                newSourceInput.focus();
                return;
            }

            if (selectedDomains.length === 0) {
                alert('请选择要更新的域名');
                return;
            }

            // 显示确认对话框
            document.getElementById('confirmNewSource').textContent = newSource;
            document.getElementById('confirmDomains').innerHTML =
                '<div class="list-group">' +
                selectedDomains.map(domain =>
                    `<div class="list-group-item">${domain}</div>`
                ).join('') +
                '</div>';

            const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            confirmModal.show();
        });

        // 确认更新
        document.getElementById('confirmUpdate').addEventListener('click', function() {
            const newSource = newSourceInput.value.trim();

            // 移除焦点以避免aria-hidden冲突
            this.blur();

            // 关闭确认对话框
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();

            // 显示进度条
            const progressBar = document.querySelector('#updateProgress .progress-bar');
            const progressContainer = document.getElementById('updateProgress');
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';

            // 禁用更新按钮
            batchUpdateBtn.disabled = true;
            batchUpdateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 更新中...';

            // 发送批量更新请求
            const formData = new FormData();
            formData.append('domains', JSON.stringify(selectedDomains));
            formData.append('new_source', newSource);
            formData.append('csrf_token', document.getElementById('csrfToken').value);

            fetch('?action=batchUpdate', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                // 隐藏进度条
                progressContainer.style.display = 'none';

                // 显示结果
                showResults(data);

                // 清除选择
                clearSelectionBtn.click();
            })
            .catch(error => {
                progressContainer.style.display = 'none';
                alert('批量更新失败: ' + error.message);
            })
            .finally(() => {
                batchUpdateBtn.disabled = false;
                batchUpdateBtn.innerHTML = '<i class="fas fa-save"></i> 批量更新源站';
            });
        });

        // 显示更新结果
        function showResults(data) {
            let resultHtml = '';

            if (data.success) {
                resultHtml += `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>${data.message}</strong>
                    </div>
                `;
            } else {
                if (data.manual_update) {
                    resultHtml += `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>${data.message}</strong>
                        </div>
                        <div class="mt-3">
                            <h5><i class="fas fa-tools"></i> 手动更新指南：</h5>
                            <ol>
                                <li>登录 <a href="https://cdn.console.aliyun.com/" target="_blank">阿里云CDN控制台</a></li>
                                <li>找到域名 "test.csmes.org" 并点击管理</li>
                                <li>在"回源配置"中修改源站地址</li>
                                <li>保存配置并等待5-10分钟生效</li>
                            </ol>
                            <div class="text-center mt-3">
                                <a href="manual_update_guide.php" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt"></i>
                                    查看详细手动更新指南
                                </a>
                            </div>
                        </div>
                    `;
                } else {
                    resultHtml += `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>${data.message}</strong>
                        </div>
                    `;
                }
            }

            if (data.results && data.results.length > 0) {
                resultHtml += '<div class="table-responsive">';
                resultHtml += '<table class="table table-sm">';
                resultHtml += '<thead><tr><th>域名</th><th>状态</th><th>消息</th></tr></thead>';
                resultHtml += '<tbody>';

                data.results.forEach(result => {
                    const statusIcon = result.success ?
                        '<i class="fas fa-check text-success"></i>' :
                        '<i class="fas fa-times text-danger"></i>';
                    const statusClass = result.success ? 'table-success' : 'table-danger';

                    resultHtml += `
                        <tr class="${statusClass}">
                            <td>${result.domain}</td>
                            <td>${statusIcon}</td>
                            <td>${result.message}</td>
                        </tr>
                    `;
                });

                resultHtml += '</tbody></table></div>';
            }

            document.getElementById('resultContent').innerHTML = resultHtml;
            const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
            resultModal.show();
        }

        // 刷新后重新加载
        document.getElementById('refreshAfterUpdate').addEventListener('click', function() {
            // 移除焦点以避免aria-hidden冲突
            this.blur();
            location.reload();
        });

        // 搜索功能
        function filterDomains() {
            const domainKeyword = searchDomainInput.value.trim().toLowerCase();
            const sourceKeyword = searchSourceInput.value.trim().toLowerCase();

            filteredDomains = allDomains.filter(domain => {
                const domainMatch = !domainKeyword || domain.domain_name.toLowerCase().includes(domainKeyword);
                const sourceMatch = !sourceKeyword || (domain.source_text && domain.source_text.toLowerCase().includes(sourceKeyword));
                return domainMatch && sourceMatch;
            });

            currentPage = 1; // 重置到第一页
            renderTable();
            updateSelectedCount();
        }

        // 渲染表格
        function renderTable() {
            const tbody = document.getElementById('domainsTableBody');
            const totalResults = filteredDomains.length;
            const totalPages = Math.ceil(totalResults / pageSize);
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, totalResults);
            const currentDomains = filteredDomains.slice(startIndex, endIndex);

            // 清空表格
            tbody.innerHTML = '';

            if (currentDomains.length === 0) {
                // 显示无数据消息
                document.getElementById('domainsTable').style.display = 'none';
                document.getElementById('paginationContainer').style.display = 'none';
                document.getElementById('resultStats').style.display = 'none';
                document.getElementById('noDataMessage').style.display = 'block';
                return;
            }

            // 显示表格
            document.getElementById('domainsTable').style.display = 'table';
            document.getElementById('noDataMessage').style.display = 'none';

            // 生成表格行
            currentDomains.forEach(domain => {
                const statusClass = domain.status === 'online' ? 'bg-success' :
                                  (domain.status === 'offline' ? 'bg-danger' : 'bg-warning');
                const statusText = domain.status === 'online' ? '运行中' :
                                 (domain.status === 'offline' ? '已停止' : '配置中');

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <input type="checkbox" class="form-check-input domain-checkbox domain-select"
                               value="${domain.domain_name}"
                               data-domain="${domain.domain_name}">
                    </td>
                    <td>
                        <strong>${domain.domain_name}</strong>
                    </td>
                    <td>
                        <span class="badge ${statusClass} status-badge">
                            ${statusText}
                        </span>
                    </td>
                    <td class="source-info">
                        ${domain.source_text || '未设置'}
                    </td>
                    <td>
                        <span class="badge bg-info status-badge">
                            ${domain.service_type}
                        </span>
                    </td>
                    <td>
                        <small class="text-muted">
                            ${domain.update_time ? new Date(domain.update_time).toLocaleString('zh-CN') : '未知'}
                        </small>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // 更新统计信息
            updateResultStats(startIndex + 1, endIndex, totalResults);

            // 更新分页控件
            updatePagination(totalPages);

            // 重新绑定选择事件
            bindSelectionEvents();
        }

        // 更新结果统计
        function updateResultStats(from, to, total) {
            document.getElementById('showingFrom').textContent = from;
            document.getElementById('showingTo').textContent = to;
            document.getElementById('totalResults').textContent = total;
            document.getElementById('resultStats').style.display = total > 0 ? 'flex' : 'none';
        }

        // 更新分页控件
        function updatePagination(totalPages) {
            const pagination = document.getElementById('pagination');
            const container = document.getElementById('paginationContainer');

            if (totalPages <= 1) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'block';
            pagination.innerHTML = '';

            // 上一页
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">上一页</a>`;
            pagination.appendChild(prevLi);

            // 页码
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            if (startPage > 1) {
                pagination.appendChild(createPageItem(1));
                if (startPage > 2) {
                    const ellipsisLi = document.createElement('li');
                    ellipsisLi.className = 'page-item disabled';
                    ellipsisLi.innerHTML = '<a class="page-link" href="#">...</a>';
                    pagination.appendChild(ellipsisLi);
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                pagination.appendChild(createPageItem(i));
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const ellipsisLi = document.createElement('li');
                    ellipsisLi.className = 'page-item disabled';
                    ellipsisLi.innerHTML = '<a class="page-link" href="#">...</a>';
                    pagination.appendChild(ellipsisLi);
                }
                pagination.appendChild(createPageItem(totalPages));
            }

            // 下一页
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">下一页</a>`;
            pagination.appendChild(nextLi);

            // 绑定分页点击事件
            pagination.querySelectorAll('.page-link[data-page]').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = parseInt(this.dataset.page);
                    if (page > 0 && page <= totalPages && page !== currentPage) {
                        currentPage = page;
                        renderTable();
                        updateSelectedCount();
                        // 滚动到表格顶部
                        document.getElementById('domainsTable').scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        }

        // 创建分页项
        function createPageItem(pageNum) {
            const li = document.createElement('li');
            li.className = `page-item ${pageNum === currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" data-page="${pageNum}">${pageNum}</a>`;
            return li;
        }

        // 初始化页面大小按钮
        function initPageSizeButtons() {
            const buttons = document.querySelectorAll('[data-page-size]');

            buttons.forEach((button) => {
                const buttonPageSize = parseInt(button.dataset.pageSize);

                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    pageSize = buttonPageSize;
                    currentPage = 1; // 重置到第一页

                    // 更新按钮样式
                    buttons.forEach(btn => {
                        if (btn.classList.contains('btn-primary')) {
                            btn.classList.remove('btn-primary');
                            btn.classList.add('btn-outline-secondary');
                        }
                        btn.classList.remove('active');
                    });

                    // 设置当前点击的按钮为选中状态
                    if (this.classList.contains('btn-outline-secondary')) {
                        this.classList.remove('btn-outline-secondary');
                    }
                    this.classList.add('btn-primary');

                    renderTable();
                    updateSelectedCount();
                });

                // 设置默认选中状态
                if (buttonPageSize === pageSize) {
                    if (button.classList.contains('btn-outline-secondary')) {
                        button.classList.remove('btn-outline-secondary');
                    }
                    button.classList.add('btn-primary');
                }
            });
        }

        // 搜索事件绑定
        searchDomainInput.addEventListener('input', function() {
            filterDomains();
        });

        searchSourceInput.addEventListener('input', function() {
            filterDomains();
        });

        // 清除搜索
        clearSearchBtn.addEventListener('click', function() {
            searchDomainInput.value = '';
            searchSourceInput.value = '';
            filterDomains();
        });

        // 重新绑定选择事件
        function bindSelectionEvents() {
            const checkboxes = document.querySelectorAll('.domain-select');

            // 单个选择
            checkboxes.forEach(checkbox => {
                // 先移除可能存在的事件监听器
                checkbox.removeEventListener('change', handleCheckboxChange);
                // 然后添加新的事件监听器
                checkbox.addEventListener('change', handleCheckboxChange);
            });

            // 更新全选状态
            updateSelectAllState();
        }

        // 处理复选框变化事件
        function handleCheckboxChange() {
            updateSelectedCount();
            updateSelectAllState();
        }
    </script>
</body>
</html>