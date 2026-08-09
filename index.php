<?php

/**
 * TinyPHP - 最简便的 PHP 框架入口
 *
 * 核心原理：spl_autoload_register 按命名空间自动加载类文件
 */

// 1. 引入自动加载器
require __DIR__ . '/tinyphp/Autoload.php';

// 2. 注册自动加载，指定命名空间对应的目录
Autoload::register([
    __DIR__ . '/tinyphp',   // 框架核心类 (tinyphp\core\*)
    __DIR__,                 // 应用类 (controller\*, model\*, utils\*)
]);

// 3. 启动路由，分发请求
\tinyphp\core\Router::dispatch();