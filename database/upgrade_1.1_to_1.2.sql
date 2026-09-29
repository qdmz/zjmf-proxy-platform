-- ============================================================
-- v1.1 -> v1.2 升级：个人资料（修改邮箱/密码）支持
-- 在 phpMyAdmin 中导入执行即可（幂等，可重复执行）
-- ============================================================
SET NAMES utf8mb4;

-- 邮件令牌表增加 data 字段：用于存放换绑邮箱时的新邮箱地址
ALTER TABLE `email_tokens`
  ADD COLUMN IF NOT EXISTS `data` VARCHAR(255) NULL DEFAULT NULL COMMENT '附加数据(如换绑新邮箱)' AFTER `token`;
