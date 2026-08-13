<?php

namespace controller;

/**
 * 默认控制器
 * URL: / 或 /index
 */
class IndexController
{
    /**
     * 默认首页
     */
    public function index()
    {
        echo '<h1>TinyPHP 框架已正常运行</h1>';
        echo '<p>PHP 版本: ' . PHP_VERSION . '</p>';
        echo '<p>可用路由示例:</p>';
        echo '<ul>';
        echo '<li><a href="/index/index">/index/index 首页</a></li>';
        echo '<li><a href="/backend/index/index">/backend/index/index 后台首页</a></li>';
        echo '<li><a href="/backend/user/detail/name/zhangsan">/backend/user/detail/name/zhangsan 用户详情</a></li>';
        echo '</ul>';
    }
}
