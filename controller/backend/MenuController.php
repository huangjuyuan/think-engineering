<?php

namespace controller\backend;

use model\MenuModel;
use utils\Auth;
use utils\Response;

/**
 * 菜单控制器
 *
 * 提供后台侧边栏菜单相关接口：
 *   GET /backend/menu/list  -> 按当前登录用户的角色返回可访问的菜单树（JWT 鉴权）
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
     * 获取侧边栏菜单树
     * GET /backend/menu/list
     *
     * 从 Authorization: Bearer <token> 解析当前用户角色，返回该角色可访问的菜单。
     * 未登录或 token 无效：返回 401 并给出提示。
     */
    public function list()
    {
        try {
            // 从 token 解析当前用户角色（不再信任前端传参）
            $user = Auth::user();
            if (!$user) {
                Response::json(null, 401, '未登录或登录已过期');
            }

            // admin 拥有全部菜单；其余角色按 RBAC 过滤
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
}
