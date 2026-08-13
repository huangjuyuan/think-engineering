<?php

namespace model;

use utils\Db;

/**
 * 角色模型（RBAC）
 *
 * 对接 te_role 表（表结构见 database/migration.sql）：
 *   id, name, title, status, created_at, updated_at
 *
 * 通过 \utils\Db 连接类访问数据库（预处理防注入）。
 * 配合 te_role_menu（角色-菜单关联）实现基于角色的菜单权限控制。
 */
class RoleModel
{
    /**
     * 获取数据库连接
     */
    private function db(): Db
    {
        return Db::instance();
    }

    /**
     * 获取角色列表
     *
     * @param bool $onlyEnabled 是否仅返回启用角色
     * @return array
     */
    public function getRoleList(bool $onlyEnabled = false): array
    {
        $where = $onlyEnabled ? 'WHERE status = 1' : '';
        return $this->db()->fetchAll(
            "SELECT id, name, title, status, created_at, updated_at
             FROM te_role $where
             ORDER BY id ASC"
        );
    }

    /**
     * 根据角色标识（name）获取角色
     *
     * @param string $name 角色标识，如 admin / user
     * @return array|null
     */
    public function getRoleByName(string $name): ?array
    {
        return $this->db()->fetch(
            "SELECT id, name, title, status FROM te_role WHERE name = ?",
            [$name]
        );
    }

    /**
     * 根据 id 获取角色
     *
     * @param int $id
     * @return array|null
     */
    public function getRoleById(int $id): ?array
    {
        return $this->db()->fetch(
            "SELECT id, name, title, status FROM te_role WHERE id = ?",
            [$id]
        );
    }

    /**
     * 新增角色
     *
     * @param array $data name, title, status?
     * @return int 新角色 ID
     * @throws \RuntimeException 参数非法或角色标识已存在
     */
    public function addRole(array $data): int
    {
        $name  = trim($data['name'] ?? '');
        $title = trim($data['title'] ?? '');

        if ($name === '' || $title === '') {
            throw new \RuntimeException('角色标识和名称不能为空');
        }
        if ($this->getRoleByName($name)) {
            throw new \RuntimeException('角色标识已存在');
        }

        return $this->db()->insert('te_role', [
            'name'   => $name,
            'title'  => $title,
            'status' => (int) ($data['status'] ?? 1),
        ]);
    }

    /**
     * 更新角色
     *
     * @param int   $id
     * @param array $data name?, title?, status?
     * @return bool
     */
    public function updateRole(int $id, array $data): bool
    {
        $update = [];
        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                throw new \RuntimeException('角色标识不能为空');
            }
            $update['name'] = $name;
        }
        if (array_key_exists('title', $data)) {
            $title = trim($data['title']);
            if ($title === '') {
                throw new \RuntimeException('角色名称不能为空');
            }
            $update['title'] = $title;
        }
        if (array_key_exists('status', $data)) {
            $update['status'] = (int) $data['status'];
        }

        if (!$update) {
            return true;
        }
        $this->db()->update('te_role', $update, 'id = ?', [$id]);
        return true;
    }

    /**
     * 删除角色（同时清理其菜单关联）
     *
     * @param int $id
     * @return bool
     */
    public function deleteRole(int $id): bool
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            // 清理角色-菜单关联
            $db->delete('te_role_menu', 'role_id = ?', [$id]);
            // 删除角色
            $db->delete('te_role', 'id = ?', [$id]);
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
