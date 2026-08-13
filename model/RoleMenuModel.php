<?php

namespace model;

use utils\Db;

/**
 * 角色-菜单关联模型（RBAC）
 *
 * 对接 te_role_menu 表（表结构见 database/migration.sql）：
 *   id, role_id, menu_id
 *
 * 负责维护"角色可访问哪些菜单"，并提供查询方法。
 * 通过 \utils\Db 连接类访问数据库（预处理防注入）。
 */
class RoleMenuModel
{
    /**
     * 获取数据库连接
     */
    private function db(): Db
    {
        return Db::instance();
    }

    /**
     * 获取某个角色已关联的全部菜单 ID
     *
     * @param int $roleId 角色 ID
     * @return int[] 菜单 ID 数组
     */
    public function getMenuIdsByRole(int $roleId): array
    {
        $rows = $this->db()->fetchAll(
            "SELECT menu_id FROM te_role_menu WHERE role_id = ?",
            [$roleId]
        );
        return array_column($rows, 'menu_id');
    }

    /**
     * 获取某个菜单被哪些角色关联
     *
     * @param int $menuId 菜单 ID
     * @return int[] 角色 ID 数组
     */
    public function getRoleIdsByMenu(int $menuId): array
    {
        $rows = $this->db()->fetchAll(
            "SELECT role_id FROM te_role_menu WHERE menu_id = ?",
            [$menuId]
        );
        return array_column($rows, 'role_id');
    }

    /**
     * 设置某个角色关联的菜单（先清空再写入，保证与传入一致）
     *
     * @param int   $roleId   角色 ID
     * @param array $menuIds  菜单 ID 数组
     * @return void
     */
    public function setRoleMenus(int $roleId, array $menuIds): void
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->delete('te_role_menu', 'role_id = ?', [$roleId]);

            $seen = [];
            foreach ($menuIds as $menuId) {
                $menuId = (int) $menuId;
                if ($menuId <= 0 || isset($seen[$menuId])) {
                    continue;
                }
                $seen[$menuId] = true;
                $db->insert('te_role_menu', ['role_id' => $roleId, 'menu_id' => $menuId]);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * 判断角色是否有权访问某个菜单
     *
     * @param int $roleId
     * @param int $menuId
     * @return bool
     */
    public function roleHasMenu(int $roleId, int $menuId): bool
    {
        $row = $this->db()->fetch(
            "SELECT id FROM te_role_menu WHERE role_id = ? AND menu_id = ?",
            [$roleId, $menuId]
        );
        return $row !== null;
    }
}
