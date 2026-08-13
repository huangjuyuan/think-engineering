<?php

namespace utils;

/**
 * 响应输出类
 *
 * 提供统一的 JSON 响应输出，控制器可直接复用。
 *
 * 自动加载约定：命名空间 utils + 类名 Response => utils/Response.php
 *
 * 用法示例：
 *   \utils\Response::json(['id' => 1], 0, 'success');
 *   \utils\Response::json(null, 1, '参数错误');
 */
class Response
{
    /**
     * 输出 JSON 并结束
     *
     * @param mixed  $data 数据
     * @param int    $code 状态码：0 成功，非 0 失败
     * @param string $msg  提示信息
     * @return void
     */
    public static function json($data, int $code = 0, string $msg = 'success'): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
