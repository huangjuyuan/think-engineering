<?php

namespace controller\backend;

use model\MenuModel;
use utils\Auth;
use utils\Response;
use utils\Validator;

/**
 * 菜单控制器
 *
 * 提供侧边栏菜单节点管理接口：
 *   GET  /backend/menu/list   -> 侧边栏菜单树（JWT 鉴权，按角色过滤）
 *   GET  /backend/menu/all    -> 全部菜单节点（平铺，节点管理页展示用）
 *   GET  /backend/menu/get?id=N -> 获取单个菜单节点
 *   POST /backend/menu/save   -> 新增/更新菜单节点
 *   POST /backend/menu/delete -> 删除菜单节点（级联删除子节点）
 */
class MenuController
{
    /**
     * 菜单模型
     */
    private $model;

    public function __construct()
    {
        $this->model = new MenuModel();
    }

    /**
     * 侧边栏菜单树（JWT 鉴权）
     * GET /backend/menu/list
     *
     * 从 Authorization: Bearer <token> 解析当前用户角色，
     * admin 返回全部菜单树，其它角色按 RBAC 过滤。
     */
    public function list()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                Response::json(null, 401, '未登录或登录已过期');
            }

            if ($user['role'] === 'admin') {
                $tree = $this->model->getMenuTree();
            } else {
                $tree = $this->model->getMenuTreeByRole($user['role']);
            }
            Response::json($tree);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 全部菜单节点（平铺，节点管理页展示用）
     * GET /backend/menu/all
     */
    public function all()
    {
        try {
            $list = $this->model->getAllList();
            Response::json(['list' => $list]);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 获取单个菜单节点
     * GET /backend/menu/get?id=N
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

            $menu = $this->model->getMenuById((int) $_GET['id']);
            if (!$menu) {
                Response::json(null, 1, '菜单节点不存在');
            }
            Response::json($menu);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 新增 / 更新菜单节点
     * POST /backend/menu/save
     * 字段：id(编辑时), pid, title, icon, url, type, sort, status
     */
    public function save()
    {
        try {
            $id   = intval($_POST['id'] ?? 0);
            $data = [
                'pid'    => intval($_POST['pid'] ?? 0),
                'title'  => trim($_POST['title'] ?? ''),
                'icon'   => trim($_POST['icon'] ?? ''),
                'url'    => trim($_POST['url'] ?? ''),
                'type'   => intval($_POST['type'] ?? 1),
                'sort'   => intval($_POST['sort'] ?? 0),
                'status' => intval($_POST['status'] ?? 1),
            ];

            $errors = Validator::check($data, [
                'title'  => 'required|max:64',
                'icon'   => 'max:64',
                'url'    => 'max:255',
                'type'   => 'integer|between:1,2',
                'sort'   => 'integer|min:0',
                'status' => 'integer|between:0,1',
            ]);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            if ($id > 0) {
                $this->model->updateMenu($id, $data);
                Response::json(['id' => $id], 0, '更新成功');
            } else {
                $newId = $this->model->addMenu($data);
                Response::json(['id' => $newId], 0, '新增成功');
            }
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 删除菜单节点（级联删除子节点）
     * POST /backend/menu/delete
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
            $this->model->deleteMenu($id);
            Response::json(['id' => $id], 0, '删除成功');
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }
}
