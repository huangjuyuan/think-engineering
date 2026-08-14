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
    `email`       VARCHAR(128)     DEFAULT NULL            COMMENT '邮箱地址',
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
INSERT INTO `te_user` (`id`, `username`, `password`, `nickname`, `role`, `status`, `email`, `avatar`) VALUES
    (1, 'admin', '$2y$10$eWg7TJwijBgaGpJ414pv3emG43riItqMhs8nHF5RtUC0ZOlYILKSK', '管理员', 'admin', 1, 'huangjuyuan@outlook.com', 'view/backend/images/avatar/20260814_ddc30801d1c46733.jpg')
ON DUPLICATE KEY UPDATE
    `password` = VALUES(`password`),
    `nickname` = VALUES(`nickname`),
    `role`     = VALUES(`role`),
    `status`   = VALUES(`status`),
    `email`    = VALUES(`email`),
    `avatar`   = VALUES(`avatar`);

INSERT INTO `te_goods` (`id`, `name`, `price`, `stock`, `description`, `img_url`, `status`) VALUES
    (5,  'TE-13BK 小型ブラシレスモータ', 3200.00,  37,  'φ13 24V 低騒音長寿命精密駆動モータ', 'view/backend/images/goods/TE-13BK.jpg', 1),
    (6,  'TE-16BK 小型ギヤードモータ',   4200.00, 176, 'φ16 高トルクタイプ、医療機器向け',   'view/backend/images/goods/TE-16BK.jpg', 1),
    (7,  'TE-20BK 低速減速モータ',       5100.00,  76, '小型減速機一体型、FA装置駆動用',     'view/backend/images/goods/TE-20BK.jpg', 1),
    (8,  'TE-22BK 標準BLDCモータ',       5600.00, 172, '24V定格電圧、汎用精密機器対応',      'view/backend/images/goods/TE-22BK.jpg', 1),
    (9,  'TE-25BK 高出力タイプ',         6400.00,  68, '高トルク・小型設計、搬送装置に最適', 'view/backend/images/goods/TE-25BK.jpg', 1),
    (10, 'TE-28BK カスタム減速モータ',   7200.00, 141, '特殊ギヤ比受注生産可能',             'view/backend/images/goods/TE-28BK.jpg', 1),
    (11, 'TE-30BK ブラシ付DCモータ',     6800.00,  42, '低コスト標準DCタイプ、小型機器',     'view/backend/images/goods/TE-30BK.jpg', 1),
    (12, 'TE-32BK 防水仕様モータ',       8900.00, 135, '防塵防水構造、屋外装置対応',         'view/backend/images/goods/TE-32BK.jpg', 1),
    (13, 'TE-35BK 大型小型ギヤード',     10500.00, 61, 'φ35最大径、高出力減速ユニット',     'view/backend/images/goods/TE-35BK.jpg', 1),
    (14, 'TE-13BR ブラシ付標準',         1900.00,  73, '低価格小型DCモータ、民生機器',       'view/backend/images/goods/TE-13BR.jpg', 1),
    (15, 'TE-16BR 減速ブラシモータ',     2400.00,  81, '小型減速一体、ゲート駆動向け',       'view/backend/images/goods/TE-16BR.jpg', 1),
    (16, 'TE-20BR 低騒音ブラシタイプ',   2900.00,  77, '静音設計、計測機器に適用',           'view/backend/images/goods/TE-20BR.jpg', 1),
    (17, 'TE-22BR 汎用DCモータ',         3100.00, 143, '12V/24V切替可能標準品',              'view/backend/images/goods/TE-22BR.jpg', 1),
    (18, 'TE-25BR 高負荷ブラシモータ',   3600.00, 104, '長時間連続運転対応',                 'view/backend/images/goods/TE-25BR.jpg', 1),
    (19, 'TE-28BR 小型減速ユニット',     4200.00,  91, '多段減速ギヤ内蔵',                   'view/backend/images/goods/TE-28BR.jpg', 1),
    (20, 'TE-30BR カスタム軸仕様',       4000.00,  30, '出力軸形状受注変更可能',             'view/backend/images/goods/TE-30BR.jpg', 1),
    (21, 'TE-32BR 小型高出力',           5200.00,  34, '省スペース大トルクモータ',           'view/backend/images/goods/TE-32BR.jpg', 1),
    (22, 'TE-35BR 産業用DCモータ',       6800.00,  67, '製造ライン搬送装置向け',             'view/backend/images/goods/TE-35BR.jpg', 1),
    (23, 'TE-S01 特殊医療用モータ',      16800.00,117, '無菌対応、医療精密機器専用',         'view/backend/images/goods/TE-S01.jpg', 1),
    (24, 'TE-S02 小型カメラ駆動',        8900.00, 143, '超小型φ10カスタムモータ',           'view/backend/images/goods/TE-S02.jpg', 1),
    (25, 'TE-S03 ロボット関節モータ',    12500.00,103, 'サーボ代替小型減速ユニット',         'view/backend/images/goods/TE-S03.jpg', 1),
    (26, 'TE-S04 ゲート開閉駆動',        9800.00, 126, '屋外防水ブラシレスモータ',           'view/backend/images/goods/TE-S04.jpg', 1),
    (27, 'TE-S05 半導体製造装置',        15600.00, 65, '低振動超高精度駆動モータ',           'view/backend/images/goods/TE-S05.jpg', 1),
    (28, 'TE-S06 小型搬送ローラー',      8200.00,  74, '連続運転長寿命設計',                 'view/backend/images/goods/TE-S06.jpg', 1),
    (29, 'TE-S07 測定機器専用',          7800.00, 191, '低トルク変動精密モータ',             'view/backend/images/goods/TE-S07.jpg', 0);

-- 商品标签（te_tags）：每个商品对应的标签（gid 关联 te_goods.id）
INSERT INTO `te_tags` (`gid`, `name`) VALUES
    (5,  'BLDC'), (5,  '無刷'), (5,  '小型'), (5,  '低騒音'), (5,  '精密'),
    (6,  'BLDC'), (6,  '無刷'), (6,  '小型'), (6,  '高トルク'), (6,  '医療'),
    (7,  'BLDC'), (7,  '無刷'), (7,  '低速'), (7,  '減速'),
    (8,  'BLDC'), (8,  '無刷'), (8,  '標準'), (8,  '精密'),
    (9,  'BLDC'), (9,  '無刷'), (9,  '高トルク'), (9,  '小型'), (9,  '搬送'),
    (10, 'BLDC'), (10, '無刷'), (10, '減速'), (10, 'カスタム'),
    (11, 'DC'),   (11, '有刷'), (11, '低コスト'), (11, '標準'),
    (12, 'BLDC'), (12, '無刷'), (12, '防水'), (12, '屋外'),
    (13, 'BLDC'), (13, '無刷'), (13, '大型'), (13, '減速'), (13, '高トルク'),
    (14, 'DC'),   (14, '有刷'), (14, '低価格'), (14, '標準'), (14, '民生'),
    (15, 'DC'),   (15, '有刷'), (15, '減速'), (15, 'ゲート'),
    (16, 'DC'),   (16, '有刷'), (16, '低騒音'), (16, '静音'), (16, '計測'),
    (17, 'DC'),   (17, '有刷'), (17, '標準'), (17, '汎用'),
    (18, 'DC'),   (18, '有刷'), (18, '高負荷'), (18, '連続運転'),
    (19, 'DC'),   (19, '有刷'), (19, '減速'), (19, '多段'),
    (20, 'DC'),   (20, '有刷'), (20, 'カスタム'), (20, '軸'),
    (21, 'DC'),   (21, '有刷'), (21, '小型'), (21, '高トルク'),
    (22, 'DC'),   (22, '有刷'), (22, '産業'), (22, '搬送'),
    (23, '特殊'), (23, '医療'), (23, '専用'), (23, '無菌'),
    (24, '特殊'), (24, 'カメラ'), (24, '超小型'),
    (25, '特殊'), (25, 'ロボット'), (25, '減速'), (25, 'サーボ'),
    (26, '特殊'), (26, 'ゲート'), (26, '防水'), (26, '屋外'), (26, '無刷'),
    (27, '特殊'), (27, '半導体'), (27, '高精度'), (27, '低振動'),
    (28, '特殊'), (28, '搬送'), (28, '長寿命'),
    (29, '特殊'), (29, '測定'), (29, '精密'), (29, '低トルク変動')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 侧边栏菜单种子数据（对应 sidebar.html 的两级结构）
-- 顶级：控制面板（type=1 目录，可展开）
-- 子级：用户管理 / 商品管理 / 节点管理 / 角色管理（type=2 菜单，可点击链接）
INSERT INTO `te_menu` (`id`, `pid`, `title`, `icon`, `url`, `type`, `sort`, `status`) VALUES
    (1, 0, '控制面板', 'icon-speedometer', NULL, 1, 1, 1),
    (2, 1, '用户管理', 'icon-user', '/view/backend/user/list.html', 2, 1, 1),
    (3, 1, '商品管理', 'icon-bag', '/view/backend/goods/list.html', 2, 2, 1),
    (5, 1, '节点管理', 'icon-layers', '/view/backend/menu/list.html', 2, 3, 1),
    (6, 1, '角色管理', 'icon-user-following', '/view/backend/role/list.html', 2, 4, 1)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `icon`  = VALUES(`icon`),
    `url`   = VALUES(`url`),
    `type`  = VALUES(`type`),
    `sort`  = VALUES(`sort`),
    `status`= VALUES(`status`);

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
-- admin 角色拥有全部菜单（1/2/3/5/6）
-- user 角色仅拥有部分菜单（1/3），看不到"节点管理/角色管理"
INSERT INTO `te_role` (`id`, `name`, `title`, `status`) VALUES
    (1, 'admin', '管理员', 1),
    (2, 'user',  '普通用户', 1)
ON DUPLICATE KEY UPDATE
    `title`  = VALUES(`title`),
    `status` = VALUES(`status`);

INSERT INTO `te_role_menu` (`role_id`, `menu_id`) VALUES
    (1, 1), (1, 2), (1, 3), (1, 5), (1, 6),  -- admin：全部菜单
    (2, 1), (2, 3)                            -- user：控制面板 + 商品管理
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);
