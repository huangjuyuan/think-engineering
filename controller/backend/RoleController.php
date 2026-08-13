<?php

namespace controller\backend;

use model\RoleMenuModel;
use model\RoleModel;
use utils\Response;
use utils\Validator;

/**
 * 角色控制器（RBAC）
 *
 * 提供角色管理接口：
 *   GET  /backend/role/list              -> 角色列表
 *   GET  /backend/role/get?id=N          -> 获取单个角色（含其菜单权限）
 *   POST /backend/role/save              -> 新增/更新角色（含菜单权限分配）
 *   POST /backend/role/delete            -> 删除角色
 *   GET  /backend/role/menus?role_id=N   -> 获取角色已分配的菜单 ID
 */
class RoleController
{
    /**
     * 角色模型
     */
    private $model;

    /**
     * 角色-菜单关联模型
     */
    private $roleMenuModel;

    public function __construct()
    {
        $this->model = new RoleModel();
        $this->roleMenuModel = new RoleMenuModel();
    }

    /**
     * 角色列表
     * GET /backend/role/list
     */
    public function list()
    {
        try {
            $list = $this->model->getRoleList();
            Response::json(['list' => $list]);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 获取单个角色
     * GET /backend/role/get?id=N
     */
    public function get()
    {
        try {
            $errors = Validator::check($_GET, [
                'id' => 'required|integer|min:1',
            ]);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            $role = $this->model->getRoleById((int) $_GET['id']);
            if (!$role) {
                Response::json(null, 1, '角色不存在');
            }
            // 附带该角色已分配的菜单 ID
            $role['menu_ids'] = $this->roleMenuModel->getMenuIdsByRole((int) $role['id']);
            Response::json($role);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 获取角色已分配的菜单 ID
     * GET /backend/role/menus?role_id=N
     */
    public function menus()
    {
        try {
            $errors = Validator::check($_GET, [
                'role_id' => 'required|integer|min:1',
            ]);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            $menuIds = $this->roleMenuModel->getMenuIdsByRole((int) $_GET['role_id']);
            Response::json($menuIds);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 新增 / 更新角色（含菜单权限分配）
     * POST /backend/role/save
     * 字段：id(编辑时), name, title, status, menu_ids(逗号分隔或数组)
     */
    public function save()
    {
        try {
            $id   = intval($_POST['id'] ?? 0);
            $data = [
                'name'   => trim($_POST['name'] ?? ''),
                'title'  => trim($_POST['title'] ?? ''),
                'status' => intval($_POST['status'] ?? 1),
            ];

            $errors = Validator::check($data, [
                'name'   => 'required|alpha_dash|max:32',
                'title'  => 'required|max:64',
                'status' => 'integer|between:0,1',
            ]);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            // 解析 menu_ids（支持逗号分隔字符串或数组）
            $menuIds = [];
            if (isset($_POST['menu_ids'])) {
                $menuIds = is_array($_POST['menu_ids'])
                    ? $_POST['menu_ids']
                    : array_filter(array_map('trim', explode(',', (string) $_POST['menu_ids'])));
                $menuIds = array_map('intval', $menuIds);
            }

            if ($id > 0) {
                $this->model->updateRole($id, $data);
                $this->roleMenuModel->setRoleMenus($id, $menuIds);
                Response::json(['id' => $id], 0, '更新成功');
            } else {
                $newId = $this->model->addRole($data);
                $this->roleMenuModel->setRoleMenus($newId, $menuIds);
                Response::json(['id' => $newId], 0, '新增成功');
            }
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 删除角色
     * POST /backend/role/delete
     * 参数：id
     */
    public function delete()
    {
        try {
            $errors = Validator::check($_POST, [
                'id' => 'required|integer|min:1',
            ]);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            $id = intval($_POST['id']);
            $this->model->deleteRole($id);
            Response::json(['id' => $id], 0, '删除成功');
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }
}
