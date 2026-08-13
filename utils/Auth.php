<?php

namespace utils;

/**
 * 认证辅助类（JWT 登录态）
 *
 * 统一封装 JWT 的生成与解析，打通登录与接口鉴权：
 *   - 登录成功后签发 token（payload 含 uid / username / role）
 *   - 需要鉴权的接口从 Authorization: Bearer <token> 解析当前用户
 *
 * 自动加载约定：命名空间 utils + 类名 Auth => utils/Auth.php
 *
 * 用法示例：
 *   // 登录成功后签发
 *   $token = \utils\Auth::issue($user);
 *
 *   // 在需要鉴权的接口中
 *   $user = \utils\Auth::user();   // 返回 ['uid'=>..,'username'=>..,'role'=>..] 或 null
 *   $role = \utils\Auth::role();   // 返回角色标识或 'guest'
 */
class Auth
{
    /**
     * JWT 签名密钥（与 UserModel::JWT_SECRET 保持一致）
     * 生产环境应改为从 config/环境变量读取
     */
    private const SECRET = 'tinyphp-user-secret-key';

    /**
     * token 有效期（秒），默认 2 小时
     */
    private const EXPIRE = 7200;

    /**
     * 签发 token
     *
     * @param array $user 用户信息，需含 id / username / role
     * @return string
     */
    public static function issue(array $user): string
    {
        $jwt = new JWT(self::SECRET);
        return $jwt->generate([
            'uid'      => (int) ($user['id'] ?? 0),
            'username' => (string) ($user['username'] ?? ''),
            'role'     => (string) ($user['role'] ?? 'user'),
        ], self::EXPIRE, 'think-engineering');
    }

    /**
     * 从当前请求解析并校验登录用户
     *
     * 从 Authorization: Bearer <token> 提取 token，校验签名与有效期，
     * 成功返回 payload（uid/username/role），失败返回 null。
     *
     * @return array|null
     */
    public static function user(): ?array
    {
        $token = JWT::getBearerToken();
        if ($token === '') {
            return null;
        }

        try {
            $jwt = new JWT(self::SECRET);
            $claims = $jwt->validate($token);
        } catch (\RuntimeException $e) {
            return null;
        }

        return [
            'uid'      => $claims['uid'] ?? 0,
            'username' => $claims['username'] ?? '',
            'role'     => $claims['role'] ?? 'guest',
        ];
    }

    /**
     * 获取当前登录用户的角色标识
     *
     * @return string 角色标识；未登录返回 'guest'
     */
    public static function role(): string
    {
        $user = self::user();
        return $user ? $user['role'] : 'guest';
    }

    /**
     * 当前是否已登录
     *
     * @return bool
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }
}
