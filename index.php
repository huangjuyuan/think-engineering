<?php

/**
 * TinyPHP - 最简便的 PHP 框架入口
 *
 * 核心原理：spl_autoload_register 按命名空间自动加载类文件
 */

// 1. 引入自动加载器
require __DIR__ . '/tinyphp/Autoload.php';

// 2. 统一引入 composer 自动加载（第三方依赖，如 symfony/yaml）
//    放在自研 Autoload 之前 require，避免后续各处重复引入
require __DIR__ . '/vendor/autoload.php';

// 3. 注册自动加载，指定命名空间对应的目录
Autoload::register([
    __DIR__ . '/tinyphp',   // 框架核心类 (tinyphp\core\*)
    __DIR__,                 // 应用类 (controller\*, model\*, utils\*)
]);

// 4. 启动路由，分发请求
\tinyphp\core\Router::dispatch();