-- zjmf-proxy-platform 升级：2026-09-30 hosts 表加 inner_ip（内网IP）
-- phpMyAdmin 执行（无存储过程权限，直接 ALTER）

SET NAMES utf8mb4;

ALTER TABLE `hosts` ADD COLUMN `inner_ip` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '内网IP' AFTER `dedicated_ip`;
