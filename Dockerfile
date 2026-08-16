# =============================================================
# TinyPHP 项目 应用容器（Apache + PHP 8.2）
# 数据库使用独立 MySQL 容器（见 docker-compose.yml 的 mysql 服务）
# =============================================================
FROM php:8.2-apache

LABEL maintainer="TinyPHP"

# ---- 系统依赖 + PHP 扩展 ----
# 只安装 PHP 运行所需扩展，MySQL 由独立容器提供
# 换用国内 Debian 镜像源（阿里云），加速 apt 下载，避免 deb.debian.org 网络慢
RUN set -eux; \
    if [ -f /etc/apt/sources.list.d/debian.sources ]; then \
        sed -i 's@deb.debian.org@mirrors.aliyun.com@g' /etc/apt/sources.list.d/debian.sources; \
        sed -i 's@security.debian.org@mirrors.aliyun.com@g' /etc/apt/sources.list.d/debian.sources; \
    elif [ -f /etc/apt/sources.list ]; then \
        sed -i 's@deb.debian.org@mirrors.aliyun.com@g' /etc/apt/sources.list; \
        sed -i 's@security.debian.org@mirrors.aliyun.com@g' /etc/apt/sources.list; \
    fi

# 修复 apt cache 目录权限并禁用易出错的 Post-Invoke 清理钩子（docker-clean）
# 该钩子会执行 rm -f /var/cache/apt/archives/*.deb，在部分 overlay 环境下会失败
RUN rm -f /etc/apt/apt.conf.d/docker-clean 2>/dev/null || true; \
    mkdir -p /var/cache/apt/archives/partial; \
    chmod -R 755 /var/cache/apt; \
    rm -rf /var/lib/apt/lists/*; \
    apt-get update

RUN apt-get install -y --no-install-recommends \
        git \
        unzip \
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

# 确保代码目录属主正确
RUN chown -R www-data:www-data /var/www/html

# 暴露 80（Apache）
EXPOSE 80

WORKDIR /var/www/html

# 前台启动 Apache（容器主进程）
CMD ["apache2-foreground"]
