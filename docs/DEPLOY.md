# 部署文档

## 一、环境要求

- PHP >= 8.0（推荐 8.1+），需扩展：`pdo_mysql`、`curl`、`openssl`、`mbstring`
- MySQL 5.7+ / MariaDB 10.3+
- Web 服务器：Nginx 或 Apache（需 URL 重写）
- 建议 PHP 函数未禁用：`curl_*`、`openssl_*`

## 二、安装步骤

### 方式一：安装向导（推荐）

1. 将项目代码上传到服务器，例如 `/www/wwwroot/shop/`，Web 根目录指向 `public/`。
2. 确保 `config/`、`storage/`、`storage/logs/` 可写。
3. 浏览器访问 `http(s)://你的域名/install.php`，按向导完成：
   - 环境检查
   - 填写数据库信息、加密密钥、站点网址、管理员账号
   - 自动建库、导入表结构、生成配置文件、创建管理员
4. **安装完成后立即删除 `public/install.php`**（或重命名）。

### 方式二：手动安装

1. 复制 `config/config.example.php` 为 `config/config.php` 并修改数据库与 `crypto_key`。
2. 导入 `database/schema.sql` 到数据库。
3. 手动插入管理员（密码用 `password_hash()` 生成）：
   ```sql
   INSERT INTO `users` (`username`,`password`,`role`,`status`) VALUES ('admin','<password_hash>','admin',1);
   ```
4. 在 `storage/` 下创建空文件 `install.lock` 防止向导被重复访问。
5. 删除 `public/install.php`。

## 三、Web 服务器配置

### Nginx

```nginx
server {
    listen 80;
    server_name shop.example.com;
    root /www/wwwroot/shop/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 禁止访问敏感文件
    location ~* \.(env|log|sql)$ { deny all; }
}
```

### Apache

项目已自带 `public/.htaccess`，确保启用 `mod_rewrite` 即可。

## 四、上游对接

1. 登录后台 `/admin` → **上游供货商** → 添加：
   - 名称：如 `主上游A`
   - API 地址：上游智简魔方面板地址（不带 `/v1`），如 `https://up.example.com`
   - 账号/密码：上游财务系统的 API 账号（需先在上游面板开启 API 并记下账号密码）
2. 点击 **测试连接**，确认登录与商品接口正常。
3. 点击 **同步产品**，将上游产品拉取到本地（新产品默认下架）。
4. 进入 **产品管理**，审核每个产品的销售价与加价策略，确认无误后**上架**。

> 注意：开通/续费时消耗的是上游 API 账号的余额，请定期在上游面板为该账号充值。

## 五、支付配置

后台 → **系统设置** → **支付设置**：

- 易支付网关地址：如 `https://pay.xxx.com`
- 商户 PID / Key：在易支付平台申请后填入
- 启用的支付方式：如 `alipay,wxpay`

易支付异步回调地址（已内置，无需手动配置）：

```
http(s)://你的域名/pay/notify/epay
```

请确保该地址可在公网访问，否则无法接收支付成功通知。
余额支付无需配置，开箱即用。

## 六、定时任务（cron）

```bash
# 每 5 分钟同步实例状态
*/5 * * * * php /www/wwwroot/shop/cron/sync_hosts.php >> /www/wwwroot/shop/storage/logs/cron.log 2>&1

# 每天 9 点到期检查与续费提醒
0 9 * * * php /www/wwwroot/shop/cron/expire_check.php >> /www/wwwroot/shop/storage/logs/cron.log 2>&1

# 每天凌晨 3 点同步上游产品价格
0 3 * * * php /www/wwwroot/shop/cron/sync_products.php >> /www/wwwroot/shop/storage/logs/cron.log 2>&1
```

到期提醒天数在后台 **系统设置 → 提醒设置** 中配置（默认 `7,3,1`）。

## 七、上线检查清单

- [ ] `public/install.php` 已删除
- [ ] `config/config.php` 中 `crypto_key` 为随机长字符串
- [ ] 上游账号余额充足
- [ ] 易支付回调地址公网可达（如使用在线支付）
- [ ] cron 定时任务已添加
- [ ] `storage/logs/` 可写且定期清理
- [ ] 已开启 HTTPS（支付场景强烈建议）

## 八、常见问题

**Q: 同步产品时提示上游登录失败？**
A: 检查 API 地址是否正确（不要带 `/v1`）、账号密码是否正确、上游是否开启了 API 功能、服务器能否访问上游地址。

**Q: 开通失败提示"上游余额支付失败"？**
A: 上游 API 账号余额不足，请到上游面板充值。

**Q: 订单已支付但开通失败？**
A: 款项已进入平台账户，订单标记为"开通失败"。可在后台订单详情页点击"重试开通"，或联系上游排查后重试。

**Q: 易支付回调后订单仍未支付？**
A: 检查 `storage/logs/app.log` 中的 `epay notify` 记录，确认签名 Key 配置正确、回调地址公网可达。
