<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 500px;
            margin: 100px auto;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            text-align: center;
            padding: 20px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-server"></i>
                        阿里云CDN域名管理系统
                    </h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="?action=authenticate">
                        <div class="mb-3">
                            <label for="access_key_id" class="form-label">
                                <i class="fas fa-key"></i>
                                Access Key ID
                            </label>
                            <input type="text" class="form-control" id="access_key_id"
                                   name="access_key_id" required
                                   placeholder="请输入阿里云Access Key ID">
                            <div class="form-text">可在阿里云控制台 AccessKey管理中获取</div>
                        </div>

                        <div class="mb-4">
                            <label for="access_secret" class="form-label">
                                <i class="fas fa-lock"></i>
                                Access Key Secret
                            </label>
                            <input type="password" class="form-control" id="access_secret"
                                   name="access_secret" required
                                   placeholder="请输入阿里云Access Key Secret">
                            <div class="form-text">密钥仅在当前会话中使用，不会存储</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt"></i>
                                登录系统
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 text-center">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt"></i>
                            您的API密钥仅用于当前会话，系统不会保存任何敏感信息
                        </small>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    需要阿里云CDN管理权限的AccessKey
                </small>
            </div>
        </div>
    </div>

    <script src="/js/bootstrap.bundle.min.js"></script>
    <script src="/js/all.min.js"></script>
</body>
</html>