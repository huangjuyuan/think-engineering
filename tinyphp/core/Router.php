<?php

namespace tinyphp\core;

/**
 * 最简路由器
 *
 * URL 规则: /controller/method/param1/param2/...
 * 默认:    /                   -> IndexController::index()
 *          /user/info          -> UserController::info()
 *          /user/info/id/123   -> UserController::info('id', '123')
 *
 * 二级目录（模块）规则:
 *          /backend/index      -> controller\backend\IndexController::index()
 *          /backend/user/detail -> controller\backend\UserController::detail()
 */
class Router
{
    private static $namespace = 'controller\\';

    /**
     * 二级目录前缀（作为模块名，映射到 controller/{dir} 命名空间）
     * 例如 backend -> controller\backend\*
     */
    private static $modules = ['backend'];

    /**
     * 分发请求
     */
    public static function dispatch(string $uri = null): void
    {
        if ($uri === null) {
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
        }

        // 去掉查询字符串
        $uri = parse_url($uri, PHP_URL_PATH);

        // 分割路径
        $segments = array_values(array_filter(explode('/', trim($uri, '/'))));

        // 默认控制器和方法
        $controller = !empty($segments) ? ucfirst($segments[0]) : 'Index';
        $method     = $segments[1] ?? 'index';
        $params     = array_slice($segments, 2);

        // 判断是否带模块前缀（二级目录）
        $ns = self::$namespace;
        if (!empty($segments) && in_array(strtolower($segments[0]), self::$modules, true)) {
            // 模块名映射到 controller/{模块名}/ 命名空间
            $ns .= strtolower($segments[0]) . '\\';
            $controller = isset($segments[1]) ? ucfirst($segments[1]) : 'Index';
            $method     = $segments[2] ?? 'index';
            $params     = array_slice($segments, 3);
        }

        // 构建完整类名
        $class = $ns . $controller . 'Controller';

        if (!class_exists($class)) {
            self::notFound("控制器 {$class} 不存在");
        }

        $instance = new $class();

        if (!method_exists($instance, $method)) {
            self::notFound("方法 {$class}::{$method}() 不存在");
        }

        // 调用控制器方法
        call_user_func_array([$instance, $method], $params);
    }

    private static function notFound(string $msg): void
    {
        http_response_code(404);
        echo "<h1>404 Not Found</h1><p>{$msg}</p>";
        exit;
    }
}
