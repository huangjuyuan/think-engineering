<?php

namespace utils;

/**
 * 文件上传处理类
 *
 * 统一处理图片上传：校验类型与大小、保存到指定目录、返回可访问的相对路径。
 *
 * 自动加载约定：命名空间 utils + 类名 Upload => utils/Upload.php
 *
 * 用法示例：
 *   try {
 *       $url = \utils\Upload::image($_FILES['image'] ?? null, 'view/backend/images/upload', 2 * 1024 * 1024);
 *       // $url 形如 'view/backend/images/upload/5f3a2c1d9e8b7.png'
 *   } catch (\RuntimeException $e) {
 *       // 处理上传失败（类型不符 / 过大 / 移动失败）
 *   }
 */
class Upload
{
    /**
     * 允许的图片 MIME 类型 => 扩展名映射
     */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * 上传图片
     *
     * @param array|null $file    $_FILES 中的单个文件数组（含 name/type/tmp_name/error/size）
     * @param string     $dir     保存目录（相对项目根目录，或绝对路径）
     * @param int        $maxSize 最大字节数，默认 2MB
     * @return string 保存后的相对 URL 路径（相对于 $dir 传参，含目录前缀）
     * @throws \RuntimeException 无文件 / 类型不符 / 超过大小 / 上传失败 / 目录不可写
     */
    public static function image($file, string $dir, int $maxSize = 2097152): string
    {
        // 1. 校验是否有文件上传
        if (!is_array($file) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('未收到有效的图片文件');
        }

        // 2. 校验大小
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException('图片大小超过限制（最大 ' . round($maxSize / 1024) . 'KB）');
        }

        // 3. 校验真实 MIME 类型（用 finfo 读取，防伪造扩展名）
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new \RuntimeException('仅支持 JPG/PNG/GIF/WEBP 格式图片');
        }
        $ext = self::ALLOWED_MIME[$mime];

        // 4. 确保目标目录存在且可写
        $rootDir = dirname(__DIR__);
        // 支持绝对路径或相对项目根目录的路径
        $fullDir = self::isAbsolute($dir) ? $dir : $rootDir . '/' . trim($dir, '/');
        if (!is_dir($fullDir) && !@mkdir($fullDir, 0755, true)) {
            throw new \RuntimeException('无法创建上传目录：' . $dir);
        }
        if (!is_writable($fullDir)) {
            throw new \RuntimeException('上传目录不可写：' . $dir);
        }

        // 5. 生成唯一文件名并保存（防覆盖、防路径注入）
        $filename = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $fullDir . '/' . $filename;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('图片保存失败，请重试');
        }

        // 6. 返回可访问的相对路径（目录 + 文件名，统一正斜杠）
        return trim($dir, '/') . '/' . $filename;
    }

    /**
     * 判断路径是否为绝对路径
     */
    private static function isAbsolute(string $path): bool
    {
        return $path !== '' && $path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
