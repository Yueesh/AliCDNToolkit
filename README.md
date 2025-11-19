# 阿里云CDN域名管理系统

基于PHP的阿里云CDN域名管理系统，用于批量管理CDN域名的源站配置。

## 主要功能

- **域名列表展示**：实时获取CDN域名列表及状态信息
- **批量源站修改**：支持选择多个域名，统一修改源站地址
- **安全认证**：使用阿里云AccessKey进行API认证
- **操作反馈**：详细的操作结果提示和错误处理

## 环境要求

- PHP 8.0+
- Composer
- cURL扩展
- 阿里云账户（需CDN管理权限）

## 安装步骤

### 1. 克隆项目
```bash
git clone https://github.com/Yueesh/AliCDNToolkit
cd AliCDNToolkit
```

### 2. 安装依赖
```bash
composer install
```

### 3. 配置Web服务器

#### Apache
将文档根目录指向 `public/` 文件夹，启用 `AllowOverride All`

#### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/project/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 4. 设置权限
```bash
chmod -R 755 public/
chmod -R 755 logs/
```

### 5. 快速测试（开发环境）
```bash
cd public/
php -S localhost:8000
```
然后访问 `http://localhost:8000`

## 使用说明

1. **登录**：使用阿里云AccessKey ID和Access Key Secret登录系统
2. **查看域名**：系统自动显示所有CDN域名及当前源站信息
3. **批量更新**：
   - 选择要修改的域名（支持全选）
   - 输入新的源站地址（支持IP或域名）
   - 点击"批量更新源站"并确认操作
4. **查看结果**：系统显示详细的更新结果

## 支持的源站格式

- IP地址：`192.168.1.100`
- 域名：`example.com`

## 安全特性

- API密钥仅存储在Session中，不持久化保存
- CSRF防护机制
- 操作前确认提示
- 严格的输入验证

## 项目结构

```
├── app/
│   ├── controllers/    # 控制器
│   ├── services/       # CDN服务类
│   └── views/          # 页面模板
├── config/             # 配置文件
├── public/             # Web根目录
├── vendor/             # 依赖包
└── logs/               # 日志目录
```

## 注意事项

1. **API权限**：AccessKey需要CDN管理权限
2. **操作谨慎**：源站修改会影响线上服务
3. **定期更新**：建议定期更换AccessKey

## 许可证

MIT License

## 联系作者

- 微信 / QQ：110765
