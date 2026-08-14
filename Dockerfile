# =============================================================
# TinyPHP 项目 单容器全套 LAMP 镜像
# 一个容器内：Apache + PHP 8.2 + MySQL 8
#
# 基于 php:8.2-apache（自带 Apache + PHP 8.2），
# 额外在容器内安装 MySQL Server 8，实现单容器全套 LAMP。
# =============================================================
FROM php:8.2-apache

LABEL maintainer="TinyPHP"

# ---- 系统依赖 + MySQL 8 安装 ----
# Debian bookworm 默认提供 MySQL 8.0
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        default-mysql-server \
        default-mysql-client \
        libicu-dev \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        intl \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ---- Apache 配置 ----
RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html

# 复制 Apache 虚拟主机配置（路由重写到 index.php）
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# ---- 复制项目代码 ----
COPY . /var/www/html

# ---- Composer 依赖（可选，联网时执行）----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

# ---- MySQL 初始化 ----
# 创建 MySQL 数据目录、初始化脚本、启动脚本
COPY docker/mysql/init-mysql.sh /usr/local/bin/init-mysql.sh
COPY docker/mysql/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY database/migration.sql /docker-entrypoint-initdb.d/01-migration.sql

RUN chmod +x /usr/local/bin/init-mysql.sh /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/lib/mysql /var/run/mysqld \
    && chown -R mysql:mysql /var/lib/mysql /var/run/mysqld \
    && chown -R www-data:www-data /var/www/html

# 暴露 80（Apache）和 3306（MySQL）
EXPOSE 80 3306

WORKDIR /var/www/html

# 启动：先初始化并启动 MySQL，再启动 Apache
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
