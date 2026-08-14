#!/bin/bash
# =============================================================
# MySQL 初始化脚本（首次启动时执行）
# - 初始化数据目录
# - 启动 mysqld
# - 创建数据库、导入 migration.sql
# - 设置 root 密码
# =============================================================
set -e

MYSQL_DATA=/var/lib/mysql
MYSQL_SOCK=/var/run/mysqld/mysqld.sock
MYSQL_PID=/var/run/mysqld/mysqld.pid
DB_ROOT_PASS="${MYSQL_ROOT_PASSWORD:-root123456}"
DB_NAME="${MYSQL_DATABASE:-think_engineering}"

# 确保目录存在且属主正确
mkdir -p /var/run/mysqld /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql

# 首次启动：数据目录为空，需要初始化
if [ ! -d "$MYSQL_DATA/mysql" ]; then
    echo "[init-mysql] 初始化 MySQL 数据目录..."
    mysqld --initialize-insecure --user=mysql --datadir="$MYSQL_DATA" 2>/dev/null || \
    mysqld --initialize-insecure --user=mysql 2>/dev/null || true
fi

# 启动 mysqld（后台）
echo "[init-mysql] 启动 mysqld..."
mysqld --user=mysql --datadir="$MYSQL_DATA" --socket="$MYSQL_SOCK" --pid-file="$MYSQL_PID" &
MYSQLD_PID=$!

# 等待 mysqld 就绪（最多 60 秒）
echo "[init-mysql] 等待 MySQL 就绪..."
for i in $(seq 1 60); do
    if mysqladmin --socket="$MYSQL_SOCK" ping >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

echo "[init-mysql] MySQL 已就绪"

# 创建数据库并导入建表脚本
echo "[init-mysql] 创建数据库 $DB_NAME 并导入 migration.sql..."
mysql --socket="$MYSQL_SOCK" -uroot <<EOF
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

mysql --socket="$MYSQL_SOCK" -uroot "$DB_NAME" < /docker-entrypoint-initdb.d/01-migration.sql 2>/dev/null || \
    echo "[init-mysql] migration.sql 导入失败（可能已初始化），跳过"

# 设置 root 密码（允许 root 从任意主机连接，便于宿主机访问）
echo "[init-mysql] 设置 root 密码..."
mysql --socket="$MYSQL_SOCK" -uroot <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$DB_ROOT_PASS';
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED WITH mysql_native_password BY '$DB_ROOT_PASS';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF

echo "[init-mysql] 初始化完成。"
