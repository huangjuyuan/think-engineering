<?php

/**
 * 最简自动加载器
 * 
 * 约定：
 *   - 命名空间即目录结构
 *   - 类名与文件名一致（.php 后缀）
 *   - 例如: tinyphp\core\Router -> tinyphp/core/Router.php
 */
class Autoload
{
    /**
     * 根目录列表（按优先级搜索）
     */
    private static $roots = [];

    /**
     * 注册自动加载
     */
    public static function register(array $roots = [])
    {
        self::$roots = $roots;
        spl_autoload_register([self::class, 'load']);
    }

    /**
     * 自动加载核心逻辑
     */
    public static function load(string $class): bool
    {
        // 将命名空间分隔符转为目录分隔符
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

        // 在所有根目录下查找
        foreach (self::$roots as $root) {
            $path = $root . DIRECTORY_SEPARATOR . $file;
            if (file_exists($path)) {
                require $path;
                return true;
            }
        }

        return false;
    }
}
