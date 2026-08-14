#!/bin/bash
# =============================================================
# 容器入口脚本（单容器 LAMP）
# 1. 确保 MySQL 已初始化并启动（后台）
# 2. 前台启动 Apache（容器主进程，保证容器持续运行）
# =============================================================
set -e

MYSQL_DATA=/var/lib/mysql
MYSQL_SOCK=/var/run/mysqld/mysqld.sock

echo "[entrypoint] 检查 MySQL 状态..."

# 若 MySQL 数据目录未初始化，则执行初始化（含启动 mysqld）
if [ ! -d "$MYSQL_DATA/mysql" ]; then
    echo "[entrypoint] 首次启动，初始化 MySQL..."
    /usr/local/bin/init-mysql.sh
else
    # 数据已存在：仅确保 mysqld 在运行
    echo "[entrypoint] MySQL 数据已存在，确保 mysqld 运行..."
    if ! mysqladmin --socket="$MYSQL_SOCK" ping >/dev/null 2>&1; then
        mkdir -p /var/run/mysqld
        chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
        mysqld --user=mysql --datadir="$MYSQL_DATA" --socket="$MYSQL_SOCK" --pid-file=/var/run/mysqld/mysqld.pid &
        # 等待就绪
        for i in $(seq 1 30); do
            mysqladmin --socket="$MYSQL_SOCK" ping >/dev/null 2>&1 && break
            sleep 1
        done
    fi
fi

echo "[entrypoint] MySQL 已就绪，启动 Apache..."

# 前台启动 Apache（容器主进程）
exec apache2-foreground
