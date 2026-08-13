<?php

namespace utils;

/**
 * JWT 生成与校验类
 *
 * 纯 PHP 实现（依赖 PHP 内置 hash_hmac / base64_encode，无需额外扩展）。
 * 支持 HS256 签名，遵循 RFC 7519 基本结构：header.payload.signature
 *
 * 自动加载约定：命名空间 utils + 类名 JWT => utils/JWT.php
 * 注意：本框架 Autoload 按「类名 = 文件名」严格匹配（Linux 大小写敏感），
 *       故文件命名为 JWT.php 以匹配类名 JWT。
 *
 * 用法示例：
 *   $jwt = new \utils\JWT('your-secret-key');
 *   $token = $jwt->generate(['uid' => 123, 'username' => 'akira'], 3600);
 *   $payload = $jwt->validate($token);           // 成功返回 payload，失败抛异常
 */
class JWT
{
    /**
     * 签名密钥
     * @var string
     */
    private $secret;

    /**
     * 签名算法（当前仅支持 HS256）
     * @var string
     */
    private $algo = 'HS256';

    /**
     * 支持的算法 -> hash_hmac 算法名映射
     * @var array
     */
    private static $algoMap = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    /**
     * 构造器
     * @param string $secret 签名密钥（务必保密，建议使用足够长的随机串）
     */
    public function __construct(string $secret = 'tinyphp-default-secret')
    {
        $this->secret = $secret;
    }

    /**
     * 生成 JWT
     *
     * @param array  $payload     业务负载（如 uid、username），会与 iat/exp/nbf 合并
     * @param int    $expire      有效期（秒），从当前时间起算；默认 3600 秒
     * @param string $issuer      签发者（可选，写入 iss）
     * @param string $audience    受众（可选，写入 aud）
     * @return string 三段式 token
     */
    public function generate(array $payload = [], int $expire = 3600, string $issuer = '', string $audience = ''): string
    {
        $now = time();

        $claims = [
            'iat' => $now, // 签发时间
            'exp' => $now + $expire, // 过期时间
            'nbf' => $now, // 生效时间
        ];

        if ($issuer !== '') {
            $claims['iss'] = $issuer;
        }
        if ($audience !== '') {
            $claims['aud'] = $audience;
        }

        // 业务负载放到标准声明之后（允许覆盖标准字段）
        $claims = array_merge($claims, $payload);

        $header = ['alg' => $this->algo, 'typ' => 'JWT'];

        $headerSegment  = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $payloadSegment = self::base64UrlEncode(json_encode($claims, JSON_UNESCAPED_UNICODE));
        $signature      = $this->sign($headerSegment . '.' . $payloadSegment);

        return $headerSegment . '.' . $payloadSegment . '.' . $signature;
    }

    /**
     * 校验 JWT：验证签名 + 生效时间 + 过期时间
     *
     * @param string $token     JWT 字符串
     * @param string $audience  可选，若传入则校验 aud 是否匹配
     * @param string $issuer    可选，若传入则校验 iss 是否匹配
     * @return array 解析并校验通过后的 payload（含 iat/exp/nbf）
     * @throws \RuntimeException 签名无效 / token 格式错误 / 未生效 / 已过期 / 受众或签发者不匹配
     */
    public function validate(string $token, string $audience = '', string $issuer = ''): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new \RuntimeException('JWT 格式错误：应为 header.payload.signature 三段');
        }

        list($headerSegment, $payloadSegment, $signature) = $parts;

        // 1. 校验签名
        $expectedSignature = $this->sign($headerSegment . '.' . $payloadSegment);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new \RuntimeException('JWT 签名校验失败');
        }

        // 2. 解析 header / payload
        $header = json_decode(self::base64UrlDecode($headerSegment), true);
        $claims = json_decode(self::base64UrlDecode($payloadSegment), true);

        if (!is_array($header) || !is_array($claims)) {
            throw new \RuntimeException('JWT 内容解析失败');
        }

        // 3. 可选：校验签发者 / 受众
        if ($issuer !== '' && (!isset($claims['iss']) || $claims['iss'] !== $issuer)) {
            throw new \RuntimeException('JWT 签发者(iss)不匹配');
        }
        if ($audience !== '' && (!isset($claims['aud']) || $claims['aud'] !== $audience)) {
            throw new \RuntimeException('JWT 受众(aud)不匹配');
        }

        // 4. 校验时间（引入少量时钟偏移容忍，避免服务器时间毫秒级误差）
        $now = time();

        if (isset($claims['nbf']) && $now + 5 < $claims['nbf']) {
            throw new \RuntimeException('JWT 尚未生效');
        }
        if (isset($claims['exp']) && $now - 5 >= $claims['exp']) {
            throw new \RuntimeException('JWT 已过期');
        }

        return $claims;
    }

    /**
     * 仅解析 JWT（不做任何校验），返回 ['header' => ..., 'payload' => ...]
     * 常用于调试或读取签名内容，判断有效性请使用 validate()
     *
     * @param string $token
     * @return array
     */
    public function parse(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new \RuntimeException('JWT 格式错误');
        }

        return [
            'header'  => json_decode(self::base64UrlDecode($parts[0]), true),
            'payload' => json_decode(self::base64UrlDecode($parts[1]), true),
        ];
    }

    /**
     * 从请求头 Authorization: Bearer <token> 中提取 token
     * 兼容 getallheaders() 不可用的环境（如 fpm）
     *
     * @return string 提取到的 token；未找到返回空字符串
     */
    public static function getBearerToken(): string
    {
        $header = '';

        // 方式一：getallheaders（apache 环境）
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $header = $value;
                    break;
                }
            }
        }

        // 方式二：直接读 $_SERVER（fpm / cli-server 常见）
        if ($header === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        }
        // 方式三：REDIRECT_HTTP_AUTHORIZATION（部分 apache rewrite 场景）
        if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * 计算签名
     * @param string $data 待签名内容（header.payload）
     * @return string base64url 编码后的签名
     */
    private function sign(string $data): string
    {
        if (!isset(self::$algoMap[$this->algo])) {
            throw new \RuntimeException("不支持的签名算法：{$this->algo}");
        }

        $hash = hash_hmac(self::$algoMap[$this->algo], $data, $this->secret, true);
        return self::base64UrlEncode($hash);
    }

    /**
     * base64url 编码（RFC 4648 URL-safe，去掉 '=' 填充）
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * base64url 解码
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
