-- ============================================================
-- 智简魔方上游代理销售平台 · 数据库结构 (MySQL 5.7+ / MariaDB)
-- 字符集 utf8mb4，安装时由 install.php 自动导入
-- ============================================================
SET NAMES utf8mb4;

-- 用户表（前台用户与管理员共用，role 区分）
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL DEFAULT '',
  `phone` VARCHAR(20) NOT NULL DEFAULT '',
  `password` VARCHAR(255) NOT NULL,
  `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1正常 0禁用',
  `email_verified_at` DATETIME NULL DEFAULT NULL COMMENT '邮箱验证时间',
  `reg_ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户';

-- 上游供货商（智简魔方 v1 开放 API）
CREATE TABLE IF NOT EXISTS `upstream_providers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT '节点名称',
  `base_url` VARCHAR(255) NOT NULL COMMENT '上游面板地址，如 https://panel.example.com',
  `account` VARCHAR(100) NOT NULL COMMENT '上游 API 登录账号（邮箱/手机）',
  `password_enc` TEXT NOT NULL COMMENT 'AES 加密后的上游密码',
  `jwt` TEXT COMMENT '缓存的 JWT',
  `jwt_expire` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'JWT 过期时间戳',
  `checkout_payment` VARCHAR(50) NOT NULL DEFAULT 'credit' COMMENT '上游购物车结算支付方式',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `remark` VARCHAR(255) NOT NULL DEFAULT '',
  `last_sync_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='上游供货商';

-- 产品（从上游同步，本地加价销售）
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_id` INT UNSIGNED NOT NULL,
  `upstream_pid` INT UNSIGNED NOT NULL,
  `type` VARCHAR(30) NOT NULL DEFAULT 'cloud' COMMENT 'hostingaccount/server/cloud/dcimcloud/dcim/bareMetal/other',
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `billingcycles` TEXT COMMENT 'JSON 支持的周期列表',
  `stock_control` TINYINT NOT NULL DEFAULT 0,
  `stock_qty` INT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '1上架 0下架',
  `sort` INT NOT NULL DEFAULT 0,
  `markup_type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent' COMMENT '加价方式',
  `markup_value` DECIMAL(10,2) NOT NULL DEFAULT 120.00 COMMENT 'percent:120=加价20%; fixed:固定加金额',
  `group_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '产品分组',
  `upstream_updated_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_provider_pid` (`provider_id`,`upstream_pid`),
  KEY `idx_provider` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='产品';

-- 产品周期价格（纵表）
CREATE TABLE IF NOT EXISTS `product_prices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `billingcycle` VARCHAR(20) NOT NULL,
  `upstream_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `upstream_setup_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '销售价',
  `setup_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '销售安装费',
  `sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠价(0=无优惠)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pp` (`product_id`,`billingcycle`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='产品周期价格';

-- 产品可配置选项
CREATE TABLE IF NOT EXISTS `product_config_options` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `upstream_option_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `option_type` TINYINT NOT NULL DEFAULT 1 COMMENT '1下拉 2单选 3是否 4数量 5操作系统 6~20见文档',
  `qty_min` INT NOT NULL DEFAULT 1,
  `qty_max` INT NOT NULL DEFAULT 1,
  `unit` VARCHAR(20) NOT NULL DEFAULT '',
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pco` (`product_id`,`upstream_option_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='产品配置选项';

-- 配置选项子项
CREATE TABLE IF NOT EXISTS `product_config_subs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `option_id` INT UNSIGNED NOT NULL,
  `upstream_sub_id` INT UNSIGNED NOT NULL,
  `option_name` VARCHAR(100) NOT NULL,
  `upstream_price_json` TEXT COMMENT '上游周期价 JSON',
  `price_json` TEXT COMMENT '销售周期价 JSON',
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pcs` (`option_id`,`upstream_sub_id`),
  KEY `idx_option` (`option_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='配置选项子项';

-- 主机实例（已开通的服务器）
CREATE TABLE IF NOT EXISTS `hosts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `provider_id` INT UNSIGNED NOT NULL,
  `upstream_host_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `domain` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '主机名',
  `username` VARCHAR(100) NOT NULL DEFAULT '',
  `password_enc` VARCHAR(255) NOT NULL DEFAULT '',
  `dedicated_ip` VARCHAR(45) NOT NULL DEFAULT '',
  `assigned_ips` TEXT,
  `os` VARCHAR(100) NOT NULL DEFAULT '',
  `port` INT NOT NULL DEFAULT 0,
  `bwlimit` VARCHAR(50) NOT NULL DEFAULT '',
  `bwusage` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '已用流量',
  `status` ENUM('pending','active','suspended','cancelled','deleted') NOT NULL DEFAULT 'pending',
  `billingcycle` VARCHAR(20) NOT NULL DEFAULT 'monthly',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `regdate` DATE NULL,
  `nextduedate` DATE NULL,
  `initiative_renew` TINYINT NOT NULL DEFAULT 0,
  `suspend_reason` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '暂停原因',
  `config_snapshot` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_upstream` (`provider_id`,`upstream_host_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='主机实例';

-- 订单
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(32) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('new','renew','upgrade','recharge') NOT NULL DEFAULT 'new',
  `product_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `host_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `billingcycle` VARCHAR(20) NOT NULL DEFAULT '',
  `qty` INT NOT NULL DEFAULT 1,
  `config_snapshot` TEXT,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `coupon_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用的优惠券ID',
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
  `status` ENUM('pending','paid','active','cancelled','failed') NOT NULL DEFAULT 'pending',
  `upstream_invoice_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `upstream_host_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `fail_reason` VARCHAR(255) NOT NULL DEFAULT '',
  `remark` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单';

-- 账单
CREATE TABLE IF NOT EXISTS `bills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bill_no` VARCHAR(32) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `host_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `type` ENUM('order','renew','upgrade','recharge') NOT NULL DEFAULT 'order',
  `title` VARCHAR(200) NOT NULL DEFAULT '',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `payment` VARCHAR(30) NOT NULL DEFAULT '' COMMENT 'balance/epay',
  `trade_no` VARCHAR(64) NOT NULL DEFAULT '',
  `upstream_invoice_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bill_no` (`bill_no`),
  KEY `idx_user` (`user_id`),
  KEY `idx_trade` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='账单';

-- 余额流水
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL COMMENT '正=入账 负=扣款',
  `balance_after` DECIMAL(10,2) NOT NULL,
  `type` VARCHAR(30) NOT NULL,
  `remark` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='余额流水';

-- 站内消息
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT,
  `is_read` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站内消息';

-- 工单
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `host_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `title` VARCHAR(200) NOT NULL,
  `status` ENUM('open','replied','closed') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单';

CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `is_admin` TINYINT NOT NULL DEFAULT 0,
  `content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单回复';

-- 系统设置
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(64) NOT NULL,
  `v` TEXT,
  `group` VARCHAR(32) NOT NULL DEFAULT 'site',
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置';

-- 管理员操作日志
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员日志';

-- 上游操作日志
CREATE TABLE IF NOT EXISTS `upstream_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `host_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `action` VARCHAR(50) NOT NULL,
  `request` TEXT,
  `response` TEXT,
  `success` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_provider` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='上游操作日志';

-- 默认设置
INSERT INTO `settings` (`k`,`v`,`group`) VALUES
('site_name','云服务器销售平台','site'),
('site_url','http://localhost','site'),
('site_icp','','site'),
('allow_register','1','site'),
('currency','元','site'),
('epay_url','','payment'),
('epay_pid','','payment'),
('epay_key','','payment'),
('epay_types','alipay,wxpay','payment'),
('renew_remind_days','7,3,1','cron'),
('mail_enabled','0','mail'),
('smtp_host','','mail'),
('smtp_port','587','mail'),
('smtp_user','','mail'),
('smtp_pass','','mail'),
('smtp_from','','mail'),
('smtp_from_name','','mail'),
('smtp_enc','tls','mail'),
('register_email_verify','0','site'),
('login_captcha','0','site')
ON DUPLICATE KEY UPDATE `v`=VALUES(`v`);

-- v1.1 新增表：邮件令牌 / 公告 / FAQ / 优惠券 / 优惠券使用 / 聊天记录 / 登录尝试
CREATE TABLE IF NOT EXISTS `email_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(20) NOT NULL COMMENT 'activate=激活, reset=重置密码, change_email=换绑邮箱',
  `token` VARCHAR(64) NOT NULL,
  `data` VARCHAR(255) NULL DEFAULT NULL COMMENT '附加数据(如换绑新邮箱)',
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_user_type` (`user_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件令牌';

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否置顶',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=发布,0=草稿',
  `published_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_pin` (`status`, `is_pinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告';

CREATE TABLE IF NOT EXISTS `faqs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(50) NOT NULL DEFAULT '常见问题',
  `question` VARCHAR(255) NOT NULL,
  `answer` TEXT NOT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=显示,0=隐藏',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='常见问题';

CREATE TABLE IF NOT EXISTS `coupons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) NOT NULL COMMENT '券码',
  `name` VARCHAR(100) NOT NULL COMMENT '名称',
  `type` ENUM('fixed','percent') NOT NULL DEFAULT 'fixed' COMMENT 'fixed=固定金额, percent=百分比',
  `value` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'fixed=抵扣金额, percent=折扣率(如85=85折)',
  `min_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '最低订单金额',
  `max_uses` INT NOT NULL DEFAULT 0 COMMENT '总可用次数，0=不限',
  `used_count` INT NOT NULL DEFAULT 0,
  `per_user_limit` INT NOT NULL DEFAULT 1 COMMENT '每用户限用次数',
  `starts_at` DATETIME NULL DEFAULT NULL,
  `ends_at` DATETIME NULL DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=启用,0=停用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券';

CREATE TABLE IF NOT EXISTS `coupon_usages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coupon_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_coupon` (`coupon_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券使用记录';

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_key` VARCHAR(64) NOT NULL,
  `user_id` INT UNSIGNED NULL DEFAULT NULL,
  `sender` ENUM('user','bot','admin') NOT NULL DEFAULT 'user',
  `message` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_key`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服聊天记录';

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `username` VARCHAR(50) NOT NULL DEFAULT '',
  `attempts` INT NOT NULL DEFAULT 0,
  `last_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ip_user` (`ip`, `username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录尝试记录';

-- 预置 FAQ
INSERT IGNORE INTO `faqs` (`id`, `category`, `question`, `answer`, `sort`, `status`) VALUES
 (1, '账号', '如何注册账号？', '点击右上角「注册」填写用户名、邮箱和密码即可。若站点开启了邮箱验证，请查收邮件完成激活。', 10, 1),
 (2, '账号', '忘记密码怎么办？', '在登录页点击「忘记密码」，输入注册邮箱，系统会发送密码重置链接（1小时内有效）。', 9, 1),
 (3, '购买', '如何购买云服务器？', '进入「云服务器」选择产品，点击详情页「立即购买」，填写主机名后提交订单并完成支付，系统会自动开通。', 8, 1),
 (4, '购买', '支持哪些支付方式？', '支持余额支付与在线支付（支付宝/微信，具体以收银台展示为准）。', 7, 1),
 (5, '使用', '服务器到期如何续费？', '进入控制台，点击对应主机进入详情页，选择续费时长并支付即可。', 6, 1),
 (6, '使用', '如何联系客服？', '可点击右下角在线客服咨询，或提交工单由技术支持回复。', 5, 1);
