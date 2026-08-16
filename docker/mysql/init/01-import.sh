#!/bin/bash
# =============================================================
# MySQL 容器初始化脚本
# 强制用 utf8mb4 连接导入 migration.sql，避免中文双重编码乱码
# 由官方 mysql:8.0 镜像在首次启动时自动执行（/docker-entrypoint-initdb.d/）
# =============================================================
set -e

echo "[init] 使用 utf8mb4 连接导入 migration.sql ..."

# 在初始化阶段，root 已由 MYSQL_ROOT_PASSWORD 设置密码
# 通过 socket 用密码连接（带默认字符集 utf8mb4）
mysql --socket=/var/run/mysqld/mysqld.sock -uroot \
  -p"${MYSQL_ROOT_PASSWORD}" \
  --default-character-set=utf8mb4 \
  "$MYSQL_DATABASE" < /docker-entrypoint-initdb.d/migration.sql

echo "[init] migration.sql 导入完成"
