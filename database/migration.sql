-- =============================================================
-- 数据库迁移脚本
-- 项目：think-engineering
-- 说明：建 user 表 与 goods 表，含索引、种子数据
-- 用法：mysql -u root -p < database/migration.sql
--      或 source database/migration.sql;
-- =============================================================

-- -------------------------------------------------------------
-- 0. 建库（若不存在）
-- -------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `think_engineering`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `think_engineering`;

-- -------------------------------------------------------------
-- 1. 用户表 te_user
--    对应 model\UserModel.php
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `te_user` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT COMMENT '用户ID',
    `username`    VARCHAR(64)      NOT NULL                COMMENT '登录用户名',
    `password`    VARCHAR(255)     NOT NULL                COMMENT '密码哈希（password_hash 生成）',
    `nickname`    VARCHAR(64)      NOT NULL DEFAULT ''     COMMENT '昵称',
    `role`        VARCHAR(32)      NOT NULL DEFAULT 'user' COMMENT '角色：admin/user',
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 1      COMMENT '状态：1启用 0禁用',
    `avatar`      VARCHAR(255)     DEFAULT NULL            COMMENT '头像地址',
    `last_login`  DATETIME         DEFAULT NULL            COMMENT '最后登录时间',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_role` (`role`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT ='用户表';

-- -------------------------------------------------------------
-- 2. 商品表 te_goods
--    对应 model\GoodsModel.php
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `te_goods` (
    `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT COMMENT '商品ID',
    `name`        VARCHAR(128)      NOT NULL                COMMENT '商品名称',
    `price`       DECIMAL(10, 2)    NOT NULL DEFAULT 0.00   COMMENT '商品单价',
    `stock`       INT UNSIGNED      NOT NULL DEFAULT 0      COMMENT '库存数量',
    `description` TEXT                                      COMMENT '商品描述',
    `img_url`     VARCHAR(128)                              COMMENT '图片地址',
    `status`      TINYINT UNSIGNED  NOT NULL DEFAULT 1      COMMENT '状态：1上架 0下架',
    `created_at`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_name` (`name`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT ='商品表';

-- -------------------------------------------------------------
-- 3. 商品标签表 te_tags
--    gid 关联 te_goods.id，一个商品可拥有多个标签
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `te_tags` (
    `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT COMMENT '标签ID',
    `gid`        INT UNSIGNED   NOT NULL                COMMENT '商品ID（关联 te_goods.id）',
    `name`       VARCHAR(64)    NOT NULL                COMMENT '标签名称',
    `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_gid` (`gid`),
    UNIQUE KEY `uk_gid_name` (`gid`, `name`),
    CONSTRAINT `fk_tags_gid` FOREIGN KEY (`gid`) REFERENCES `te_goods` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT ='商品标签表';

-- -------------------------------------------------------------
-- 4. 种子数据
--    te_user 密码为 password_hash('123456') 的结果，登录默认 admin/123456
-- -------------------------------------------------------------
INSERT INTO `te_user` (`username`, `password`, `nickname`, `role`) VALUES
    ('admin', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFIrO2z0tVHm3mQz3d5sKxTq5g3n2y5e', '管理员', 'admin')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

INSERT INTO `te_goods` (`name`, `price`, `stock`, `description`) VALUES
    ('商品1', 9.90, 100, '演示商品1'),
    ('商品2', 19.90, 50, '演示商品2'),
    ('商品3', 29.90, 20, '演示商品3');

INSERT INTO `te_tags` (`gid`, `name`) VALUES
    (1, '人气'),
    (1, '新作'),
    (2, '特价'),
    (3, '进口品'),
    (3, '限定');
