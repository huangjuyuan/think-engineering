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
-- 4. 侧边栏菜单节点表 te_menu
--    对应后台侧边栏（view/backend/common/sidebar.html）动态渲染的菜单结构
--    支持多级菜单：pid 关联父节点（0 为顶级）
--    例：控制面板(顶级) -> 用户管理 / 商品管理（子节点）
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `te_menu` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT COMMENT '菜单ID',
    `pid`        INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '父节点ID，0 为顶级目录',
    `title`      VARCHAR(64)      NOT NULL                COMMENT '菜单名称',
    `icon`       VARCHAR(64)      DEFAULT NULL            COMMENT '图标 class（如 icon-user）',
    `url`        VARCHAR(255)     DEFAULT NULL            COMMENT '链接地址（子菜单项使用，顶级目录可为空）',
    `type`       TINYINT UNSIGNED NOT NULL DEFAULT 1      COMMENT '类型：1目录(可展开) 2菜单(可点击链接)',
    `sort`       INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '排序权重，越小越靠前',
    `status`     TINYINT UNSIGNED NOT NULL DEFAULT 1      COMMENT '状态：1启用 0禁用',
    `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_pid` (`pid`),
    KEY `idx_sort` (`sort`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT ='侧边栏菜单节点表';

-- -------------------------------------------------------------
-- 5. 种子数据
--    te_user 密码为 password_hash('123456') 的结果，登录默认 admin/123456
-- -------------------------------------------------------------
INSERT INTO `te_user` (`username`, `password`, `nickname`, `role`) VALUES
    ('admin', '$2y$10$eWg7TJwijBgaGpJ414pv3emG43riItqMhs8nHF5RtUC0ZOlYILKSK', '管理员', 'admin')
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

-- 侧边栏菜单种子数据（对应 sidebar.html 的两级结构）
-- 顶级：控制面板（type=1 目录，可展开）
-- 子级：用户管理 / 商品管理（type=2 菜单，可点击链接）
INSERT INTO `te_menu` (`pid`, `title`, `icon`, `url`, `type`, `sort`) VALUES
    (0, '控制面板', 'icon-speedometer', NULL, 1, 1),
    (1, '用户管理', 'icon-user', '/view/backend/user/list.html', 2, 1),
    (1, '商品管理', 'icon-bag', '/view/backend/goods/list.html', 2, 2);

-- -------------------------------------------------------------
-- 6. 角色表 te_role（RBAC）
--    对应 te_user.role 字段（角色标识），如 admin / user
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `te_role` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT COMMENT '角色ID',
    `name`       VARCHAR(32)      NOT NULL                COMMENT '角色标识（如 admin/user）',
    `title`      VARCHAR(64)      NOT NULL                COMMENT '角色名称（如 管理员）',
    `status`     TINYINT UNSIGNED NOT NULL DEFAULT 1      COMMENT '状态：1启用 0禁用',
    `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT ='角色表';

-- -------------------------------------------------------------
-- 7. 角色-菜单关联表 te_role_menu（RBAC）
--    决定某个角色可访问哪些菜单节点
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `te_role_menu` (
    `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '关联ID',
    `role_id` INT UNSIGNED NOT NULL COMMENT '角色ID（关联 te_role.id）',
    `menu_id` INT UNSIGNED NOT NULL COMMENT '菜单ID（关联 te_menu.id）',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_role_menu` (`role_id`, `menu_id`),
    KEY `idx_menu_id` (`menu_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT ='角色-菜单关联表';

-- RBAC 种子数据：
-- admin 角色拥有全部菜单（1/2/3）
-- user 角色仅拥有部分菜单（1/2），看不到"商品管理"
INSERT INTO `te_role` (`name`, `title`) VALUES
    ('admin', '管理员'),
    ('user', '普通用户');

INSERT INTO `te_role_menu` (`role_id`, `menu_id`) VALUES
    (1, 1), (1, 2), (1, 3),   -- admin：全部菜单
    (2, 1), (2, 2);            -- user：控制面板 + 用户管理
