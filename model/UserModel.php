<?php

namespace model;

use utils\Db;
use utils\JWT;

/**
 * 用户模型
 *
 * 对接 te_user 表（表结构见 database/migration.sql）：
 *   id, username, password, nickname, role, status, last_login, created_at, updated_at
 *
 * 通过 \utils\Db 连接类访问数据库（预处理防注入）。
 * 密码统一使用 password_hash() / password_verify()。
 */
class UserModel
{
    /**
     * JWT 签名密钥（生产环境请改为从 config/环境变量读取）
     */
    private const JWT_SECRET = 'tinyphp-user-secret-key';

    /**
     * 获取数据库连接
     */
    private function db(): Db
    {
        return Db::instance();
    }

    /**
     * 分页查询用户列表
     *
     * @param int    $page      页码（从 1 开始）
     * @param int    $pageSize  每页条数
     * @param string $keyword   可选关键字，模糊匹配 username / nickname
     * @param int    $status    可选状态过滤，-1 表示不过滤
     * @return array ['total' => 总数, 'list' => 用户列表]
     */
    public function getUserList(int $page = 1, int $pageSize = 10, string $keyword = '', int $status = -1): array
    {
        $db = $this->db();
        $where = [];
        $bind = [];

        if ($keyword !== '') {
            $where[] = '(username LIKE ? OR nickname LIKE ?)';
            $like = '%' . $keyword . '%';
            $bind[] = $like;
            $bind[] = $like;
        }
        if ($status >= 0) {
            $where[] = 'status = ?';
            $bind[] = $status;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // 总数
        $total = (int) $db->scalar("SELECT COUNT(*) FROM te_user $whereSql", $bind);

        // 分页列表（不返回 password 哈希）
        $offset = ($page - 1) * $pageSize;
        $list = $db->fetchAll(
            "SELECT id, username, nickname, role, status, avatar, last_login, created_at, updated_at
             FROM te_user $whereSql
             ORDER BY id DESC
             LIMIT $pageSize OFFSET $offset",
            $bind
        );

        return ['total' => $total, 'list' => $list];
    }

    /**
     * 按 ID 获取用户（不含密码）
     *
     * @param int $id
     * @return array|null
     */
    public function getUserById(int $id): ?array
    {
        return $this->db()->fetch(
            "SELECT id, username, nickname, role, status, avatar, last_login, created_at, updated_at
             FROM te_user WHERE id = ?",
            [$id]
        );
    }

    /**
     * 按用户名获取用户（含密码哈希，用于登录校验）
     *
     * @param string $username
     * @return array|null
     */
    public function getUserByUsername(string $username): ?array
    {
        return $this->db()->fetch("SELECT * FROM te_user WHERE username = ?", [$username]);
    }

    /**
     * 新增用户
     *
     * @param array $data username, nickname, password, email? role?, status?
     * @return int 新用户 ID
     * @throws \RuntimeException 用户名已存在
     */
    public function addUser(array $data): int
    {
        $username = trim($data['username'] ?? '');
        $nickname = trim($data['nickname'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $nickname === '' || $password === '') {
            throw new \RuntimeException('用户名、昵称、密码不能为空');
        }
        // 检查用户名唯一
        if ($this->getUserByUsername($username)) {
            throw new \RuntimeException('用户名已存在');
        }

        return $this->db()->insert('te_user', [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'nickname' => $nickname,
            'role'     => $data['role'] ?? 'user',
            'status'   => (int) ($data['status'] ?? 1),
            'avatar'   => $data['avatar'] ?? null,
        ]);
    }

    /**
     * 更新用户信息
     *
     * @param int   $id
     * @param array $data 可包含 nickname, role, status, last_login；password 非空则重置密码
     * @return bool
     * @throws \RuntimeException 用户不存在
     */
    public function updateUser(int $id, array $data): bool
    {
        $db = $this->db();
        $user = $this->getUserById($id);
        if (!$user) {
            throw new \RuntimeException('用户不存在');
        }

        $update = [];
        if (isset($data['nickname'])) $update['nickname'] = $data['nickname'];
        if (isset($data['role']))     $update['role']     = $data['role'];
        if (isset($data['status']))   $update['status']   = (int) $data['status'];
        if (isset($data['last_login'])) $update['last_login'] = $data['last_login'];
        if (array_key_exists('avatar', $data)) $update['avatar'] = $data['avatar'];
        // 密码非空则重新哈希
        if (!empty($data['password'])) {
            $update['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (!$update) {
            return true;
        }

        $db->update('te_user', $update, 'id = ?', [$id]);
        return true;
    }

    /**
     * 删除用户
     *
     * @param int $id
     * @return bool
     */
    public function deleteUser(int $id): bool
    {
        return $this->db()->delete('te_user', 'id = ?', [$id]) > 0;
    }

    /**
     * 判断用户密码是否正确
     *
     * @param string $username 用户名
     * @param string $password 明文密码
     * @return array ['ok' => bool, 'user' => 用户信息|null]
     */
    public function checkPassword(string $username, string $password): array
    {
        $user = $this->getUserByUsername($username);
        if (!$user) {
            return ['ok' => false, 'user' => null];
        }
        if (!password_verify($password, $user['password'])) {
            return ['ok' => false, 'user' => null];
        }
        unset($user['password']);
        return ['ok' => true, 'user' => $user];
    }

    /**
     * 按用户名获取公开用户信息（不含密码哈希）
     *
     * @param string $username
     * @return array|null
     */
    public function getUserInfo(string $username): ?array
    {
        $user = $this->getUserByUsername($username);
        if ($user) {
            unset($user['password']);
        }
        return $user;
    }

    /**
     * 登录成功记录 last_login
     *
     * @param int $id
     * @return void
     */
    public function updateLastLogin(int $id): void
    {
        $this->db()->update('te_user', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    /**
     * 校验 JWT 并确认用户身份
     * （保留原有 checkToken 逻辑，校验签名、用户名匹配、签发时间等）
     *
     * @param string $username
     * @param string $token
     * @param string $start_date
     * @param int $expire_time
     * @return array
     */
    public function checkToken(string $username, string $token, string $start_date, int $expire_time)
    {
        $jwt = new JWT(self::JWT_SECRET);

        try {
            $claims = $jwt->validate($token);
        } catch (\RuntimeException $e) {
            return ['error' => $e->getMessage()];
        }

        if (!isset($claims['username']) || $claims['username'] !== $username) {
            return ['error' => 'token 用户名与当前用户不匹配'];
        }

        if ($start_date !== '' && $start_date !== '0') {
            $startTs = strtotime($start_date);
            if ($startTs === false) {
                return ['error' => 'start_date 格式非法'];
            }
            if (($claims['iat'] ?? 0) < $startTs) {
                return ['error' => 'token 签发时间早于允许的最早时间'];
            }
        }

        if (!isset($claims['exp']) && $start_date !== '' && $start_date !== '0') {
            $deadlineTs = strtotime($start_date) + $expire_time;
            if (time() > $deadlineTs) {
                return ['error' => 'token 已超过有效期'];
            }
        }

        $userInfo = $this->getUserInfo($username);
        return array_merge($userInfo ?: [], [
            'token_claims' => $claims,
        ]);
    }
}
