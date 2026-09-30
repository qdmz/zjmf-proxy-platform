-- zjmf-proxy-platform 升级：2026-09-30 实例同步新增字段
-- 在 phpMyAdmin 中对演示库执行一次即可（IF NOT EXISTS 逻辑用存储过程兼容写法）

SET NAMES utf8mb4;

-- hosts 表新增 bwusage（已用流量）、suspend_reason（暂停原因）
-- MySQL 不支持 ADD COLUMN IF NOT EXISTS，用信息表判断

DELIMITER $$

DROP PROCEDURE IF EXISTS `zjmf_add_host_cols`$$

CREATE PROCEDURE `zjmf_add_host_cols`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosts' AND COLUMN_NAME = 'bwusage') THEN
        ALTER TABLE `hosts` ADD COLUMN `bwusage` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '已用流量' AFTER `bwlimit`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosts' AND COLUMN_NAME = 'suspend_reason') THEN
        ALTER TABLE `hosts` ADD COLUMN `suspend_reason` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '暂停原因' AFTER `initiative_renew`;
    END IF;
END$$

DELIMITER ;

CALL `zjmf_add_host_cols`();
DROP PROCEDURE `zjmf_add_host_cols`;
