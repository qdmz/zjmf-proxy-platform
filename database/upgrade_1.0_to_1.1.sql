SET NAMES utf8mb4;
-- ============================================================
-- 智简魔方代理销售平台 v1.1 功能升级迁移
-- 适用：已安装 v1.0 的站点（全新安装请直接使用 database/schema.sql）
-- 执行：mysql -u xxx -p dbname < database/upgrade_1.0_to_1.1.sql
-- ============================================================

-- 1. 邮件令牌（注册激活 / 密码重置）
CREATE TABLE IF NOT EXISTS `email_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(20) NOT NULL COMMENT 'activate=激活, reset=重置密码',
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_user_type` (`user_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件令牌';

-- 2. 公告
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

-- 3. 常见问题
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

-- 4. 优惠券
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

-- 5. 优惠券使用记录
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

-- 6. 在线客服聊天记录
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

-- 7. 登录尝试（防暴力破解）
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `username` VARCHAR(50) NOT NULL DEFAULT '',
  `attempts` INT NOT NULL DEFAULT 0,
  `last_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ip_user` (`ip`, `username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录尝试记录';

-- 8. 用户表：邮箱验证时间
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email_verified_at` DATETIME NULL DEFAULT NULL COMMENT '邮箱验证时间' AFTER `status`;

-- 9. 订单表：优惠券关联
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `coupon_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用的优惠券ID' AFTER `amount`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额' AFTER `coupon_id`;

-- 10. 新增系统设置默认值
INSERT IGNORE INTO `settings` (`k`, `v`, `group`) VALUES
 ('mail_enabled', '0', 'mail'),
 ('smtp_host', '', 'mail'),
 ('smtp_port', '587', 'mail'),
 ('smtp_user', '', 'mail'),
 ('smtp_pass', '', 'mail'),
 ('smtp_from', '', 'mail'),
 ('smtp_from_name', '', 'mail'),
 ('smtp_enc', 'tls', 'mail'),
 ('register_email_verify', '0', 'site'),
 ('login_captcha', '0', 'site');

-- 11. 预置演示 FAQ（可选）
INSERT IGNORE INTO `faqs` (`id`, `category`, `question`, `answer`, `sort`, `status`) VALUES
 (1, '账号', '如何注册账号？', '点击右上角「注册」填写用户名、邮箱和密码即可。若站点开启了邮箱验证，请查收邮件完成激活。', 10, 1),
 (2, '账号', '忘记密码怎么办？', '在登录页点击「忘记密码」，输入注册邮箱，系统会发送密码重置链接（1小时内有效）。', 9, 1),
 (3, '购买', '如何购买云服务器？', '进入「云服务器」选择产品，点击详情页「立即购买」，填写主机名后提交订单并完成支付，系统会自动开通。', 8, 1),
 (4, '购买', '支持哪些支付方式？', '支持余额支付与在线支付（支付宝/微信，具体以收银台展示为准）。', 7, 1),
 (5, '使用', '服务器到期如何续费？', '进入控制台，点击对应主机进入详情页，选择续费时长并支付即可。', 6, 1),
 (6, '使用', '如何联系客服？', '可点击右下角在线客服咨询，或提交工单由技术支持回复。', 5, 1);
