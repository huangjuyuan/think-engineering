<?php

namespace utils;

use Symfony\Component\Yaml\Yaml;

/**
 * 配置读取类（YAML 格式）
 *
 * 统一封装 config/*.yaml 配置文件的读取与缓存，
 * 依赖 symfony/yaml（通过 composer 引入，需先加载 vendor/autoload.php）。
 *
 * 自动加载约定：命名空间 utils + 类名 Config => utils/Config.php
 *
 * 用法示例：
 *   $dbConfig  = \utils\Config::get('database');        // 读 config/database.yaml
 *   $dbDefault = \utils\Config::get('database.default'); // 取嵌套键（点号路径）
 */
class Config
{
    /**
     * 已解析的配置缓存（文件路径 => 解析结果）
     * @var array<string, array>
     */
    private static $cache = [];

    /**
     * 读取一个配置文件并返回解析后的数组
     *
     * @param string $name 配置文件名（不含 .yaml 后缀），如 'database'
     * @return array 解析后的配置数组；文件不存在或解析失败返回空数组
     */
    public static function get(string $name): array
    {
        if (!isset(self::$cache[$name])) {
            $file = self::filePath($name);
            if (is_file($file) && class_exists('\Symfony\Component\Yaml\Yaml')) {
                $parsed = Yaml::parseFile($file);
                self::$cache[$name] = is_array($parsed) ? $parsed : [];
            } else {
                self::$cache[$name] = [];
            }
        }
        return self::$cache[$name];
    }

    /**
     * 读取配置的嵌套键，支持点号路径，如 'database.default.host'
     *
     * @param string $name 配置文件名（不含 .yaml）
     * @param string $key  嵌套键路径，如 'default.host'
     * @param mixed  $default 键不存在时的默认值
     * @return mixed
     */
    public static function key(string $name, string $key, $default = null)
    {
        $config = self::get($name);
        $nodes  = explode('.', $key);

        $current = $config;
        foreach ($nodes as $node) {
            if (is_array($current) && array_key_exists($node, $current)) {
                $current = $current[$node];
            } else {
                return $default;
            }
        }
        return $current;
    }

    /**
     * 直接根据完整文件路径解析一个 YAML 文件（不缓存）
     *
     * 用于读取不在 config/ 目录下的其他 YAML 配置（如仓位策略）。
     *
     * @param string $file YAML 文件绝对/相对路径
     * @return array
     */
    public static function parseFile(string $file): array
    {
        if (is_file($file) && class_exists('\Symfony\Component\Yaml\Yaml')) {
            $parsed = Yaml::parseFile($file);
            return is_array($parsed) ? $parsed : [];
        }
        return [];
    }

    /**
     * 组装配置文件的完整路径（config/{name}.yaml）
     *
     * @param string $name
     * @return string
     */
    private static function filePath(string $name): string
    {
        return dirname(__DIR__) . '/config/' . $name . '.yaml';
    }
}
