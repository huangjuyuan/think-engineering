<?php

namespace model;

use utils\Db;

/**
 * 侧边栏菜单模型
 *
 * 对接 te_menu 表（表结构见 database/migration.sql）：
 *   id, pid, title, icon, url, type, sort, status, created_at, updated_at
 *
 * 通过 \utils\Db 连接类访问数据库（预处理防注入）。
 * 用于后台侧边栏菜单的动态渲染。
 */
class MenuModel
{
    /**
     * 获取数据库连接
     */
    private function db(): Db
    {
        return Db::instance();
    }

    /**
     * 获取启用的菜单节点列表（未组装成树，按父级+排序排列）
     *
     * @return array 菜单节点数组
     */
    public function getEnabledList(): array
    {
        return $this->db()->fetchAll(
            "SELECT id, pid, title, icon, url, type, sort
             FROM te_menu
             WHERE status = 1
             ORDER BY pid ASC, sort ASC, id ASC"
        );
    }

    /**
     * 获取菜单树（多级结构，子节点挂到父节点的 children 下）
     *
     * @param array|null $allowedIds 允许的菜单 ID 数组；null 表示不限制（返回全部）
     * @return array 树形菜单数组
     */
    public function getMenuTree(array $allowedIds = null): array
    {
        $list = $this->getEnabledList();
        if (!$list) {
            return [];
        }

        // 若指定了允许的菜单 ID，先过滤列表
        if ($allowedIds !== null) {
            $allowMap = array_flip($allowedIds);
            $list = array_values(array_filter($list, function ($node) use ($allowMap) {
                return isset($allowMap[$node['id']]);
            }));
        }

        // 按 id 建立索引
        $nodes = [];
        foreach ($list as $node) {
            $node['children'] = [];
            $nodes[$node['id']] = $node;
        }

        // 组装树：子节点挂到父节点；父节点不在允许列表时，子节点提升为顶级
        $tree = [];
        foreach ($nodes as $id => &$node) {
            if ($node['pid'] > 0 && isset($nodes[$node['pid']])) {
                $nodes[$node['pid']]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return $tree;
    }

    /**
     * 按角色获取可访问的菜单树（RBAC）
     *
     * 流程：角色名 -> 角色ID -> 角色可访问的菜单ID集合 -> 过滤菜单树。
     * admin 角色视为超级管理员，拥有全部菜单。
     *
     * @param string $roleName 角色标识（如 admin / user）
     * @return array 该角色可访问的菜单树
     */
    public function getMenuTreeByRole(string $roleName): array
    {
        // admin 拥有全部菜单
        if ($roleName === 'admin') {
            return $this->getMenuTree();
        }

        $role = (new RoleModel())->getRoleByName($roleName);
        if (!$role) {
            return [];
        }

        $menuIds = (new RoleMenuModel())->getMenuIdsByRole((int) $role['id']);
        if (!$menuIds) {
            return [];
        }

        return $this->getMenuTree($menuIds);
    }

    /**
     * 根据 id 获取单个菜单节点
     *
     * @param int $id
     * @return array|null
     */
    public function getMenuById(int $id): ?array
    {
        return $this->db()->fetch("SELECT * FROM te_menu WHERE id = ?", [$id]);
    }

    /**
     * 新增菜单节点
     *
     * @param array $data pid, title, icon?, url?, type, sort?, status?
     * @return int 新菜单 ID
     */
    public function addMenu(array $data): int
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw new \RuntimeException('菜单名称不能为空');
        }

        return $this->db()->insert('te_menu', [
            'pid'    => (int) ($data['pid'] ?? 0),
            'title'  => $title,
            'icon'   => $data['icon'] ?? null,
            'url'    => $data['url'] ?? null,
            'type'   => (int) ($data['type'] ?? 1),
            'sort'   => (int) ($data['sort'] ?? 0),
            'status' => (int) ($data['status'] ?? 1),
        ]);
    }

    /**
     * 更新菜单节点
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public function updateMenu(int $id, array $data): bool
    {
        $update = [];
        if (array_key_exists('pid', $data))    $update['pid']    = (int) $data['pid'];
        if (array_key_exists('title', $data))  $update['title']  = trim($data['title']);
        if (array_key_exists('icon', $data))   $update['icon']   = $data['icon'];
        if (array_key_exists('url', $data))    $update['url']    = $data['url'];
        if (array_key_exists('type', $data))   $update['type']   = (int) $data['type'];
        if (array_key_exists('sort', $data))   $update['sort']   = (int) $data['sort'];
        if (array_key_exists('status', $data)) $update['status'] = (int) $data['status'];

        if (!$update) {
            return true;
        }
        $this->db()->update('te_menu', $update, 'id = ?', [$id]);
        return true;
    }

    /**
     * 删除菜单节点（同时级联删除其所有子节点）
     *
     * @param int $id
     * @return bool
     */
    public function deleteMenu(int $id): bool
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            // 递归收集该节点及其所有子孙 id
            $ids = $this->collectChildrenIds([$id]);
            if ($ids) {
                $place = implode(',', array_fill(0, count($ids), '?'));
                $db->delete('te_menu', "id IN ($place)", $ids);
            }
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * 收集一个节点及其全部子孙节点 id（用于级联删除）
     *
     * @param array $ids 起始 id 集合
     * @return array 所有关联 id
     */
    private function collectChildrenIds(array $ids): array
    {
        $all = $ids;
        $result = $ids;
        while (!empty($ids)) {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $rows = $this->db()->fetchAll("SELECT id FROM te_menu WHERE pid IN ($place) AND status IN (0,1)", $ids);
            $ids = array_column($rows, 'id');
            $result = array_merge($result, $ids);
        }
        return $result;
    }
}
