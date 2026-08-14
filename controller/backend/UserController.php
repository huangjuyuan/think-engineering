<?php

namespace controller\backend;

use model\UserModel;
use utils\Auth;
use utils\Response;
use utils\Upload;
use utils\Validator;

/**
 * 用户控制器
 *
 * 提供 JSON API：
 *   GET  /backend/user/list          -> 用户列表（分页/搜索）
 *   GET  /backend/user/get?id=N      -> 获取单个用户
 *   POST /backend/user/save          -> 新增/更新用户
 *   POST /backend/user/delete        -> 删除用户
 *   POST /backend/user/login         -> 登录校验
 *   POST /backend/user/register      -> 注册
 *
 * 数据通过 \model\UserModel 对接数据库 te_user 表。
 */
class UserController
{
    /**
     * 用户模型
     */
    private $model;

    public function __construct()
    {
        $this->model = new UserModel();
    }

    /**
     * 用户列表
     * GET /backend/user/list
     * 参数：page, page_size, keyword, status
     */
    public function list()
    {
        try {
            $errors = Validator::check($_GET, [
                'page'      => 'integer|min:1',
                'page_size' => 'integer|between:1,100',
                'status'    => 'integer|between:-1,1',
            ]);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            $page     = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 10)));
            $keyword  = trim($_GET['keyword'] ?? '');
            $status   = intval($_GET['status'] ?? -1);

            $result = $this->model->getUserList($page, $pageSize, $keyword, $status);
            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 获取单个用户（编辑页回填）
     * GET /backend/user/get?id=1
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

            $id   = intval($_GET['id']);
            $user = $this->model->getUserById($id);
            if (!$user) {
                Response::json(null, 1, '用户不存在');
            }
            Response::json($user);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    // getInfo 作为 get 的别名（兼容旧路由）
    public function getInfo()
    {
        $this->get();
    }

    /**
     * 新增 / 更新用户
     * POST /backend/user/save
     * 字段：id(编辑时), username, nickname, password, role, status
     */
    public function save()
    {
        try {
            $id = intval($_POST['id'] ?? 0);
            $data = [
                'username' => trim($_POST['username'] ?? ''),
                'nickname' => trim($_POST['nickname'] ?? ''),
                'email'    => trim($_POST['email'] ?? ''),
                'password' => trim($_POST['password'] ?? ''),
                'role'     => $_POST['role'] ?? 'user',
                'status'   => intval($_POST['status'] ?? 1),
            ];

            // 处理头像上传（若上传了新头像则写入 avatar）
            if (isset($_FILES['avatar']) && is_array($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                $data['avatar'] = Upload::image($_FILES['avatar'], 'view/backend/images/upload', 2 * 1024 * 1024);
            }

            // 新增时必须设置密码；编辑时密码可选（留空则不修改）
            $rules = [
                'username' => 'required|alpha_dash|min:3|max:64',
                'nickname' => 'required|max:64',
                'email'    => 'email|max:128',
                'role'     => 'in:admin,user',
                'status'   => 'integer|between:0,1',
            ];
            if ($id > 0) {
                $rules['password'] = 'max:32';
            } else {
                $rules['password'] = 'required|min:6|max:32';
            }

            $errors = Validator::check($data, $rules);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            if ($id > 0) {
                $update = [
                    'nickname' => $data['nickname'],
                    'email'    => $data['email'],
                    'role'     => $data['role'],
                    'status'   => $data['status'],
                    'password' => $data['password'],
                ];
                if (isset($data['avatar'])) {
                    $update['avatar'] = $data['avatar'];
                }
                $this->model->updateUser($id, $update);
                Response::json(['id' => $id], 0, '更新成功');
            } else {
                $newId = $this->model->addUser($data);
                Response::json(['id' => $newId], 0, '新增成功');
            }
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 登录
     * POST /backend/user/login
     * 字段：username, password
     */
    public function login()
    {
        try {
            $errors = Validator::check($_POST, [
                'username' => 'required|max:64',
                'password' => 'required|max:32',
            ]);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            $username = trim($_POST['username']);
            $password = trim($_POST['password']);

            $result = $this->model->checkPassword($username, $password);
            if (!$result['ok']) {
                Response::json(null, 1, $result['message'] ?? '用户名或密码错误');
            }

            $user = $result['user'];
            $this->model->updateLastLogin((int) $user['id']);

            // 签发真正的 JWT（含 uid / username / role）
            $token = \utils\Auth::issue($user);

            Response::json([
                'token' => $token,
                'user'  => $user,
            ], 0, '登录成功');
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 注册
     * POST /backend/user/register
     * 字段：username, nickname, password
     */
    public function register()
    {
        try {
            $data = [
                'username' => trim($_POST['username'] ?? ''),
                'nickname' => trim($_POST['nickname'] ?? ''),
                'email'    => trim($_POST['email'] ?? ''),
                'password' => trim($_POST['password'] ?? ''),
                'role'     => 'user',
                'status'   => 1,
            ];

            $errors = Validator::check($data, [
                'username' => 'required|alpha_dash|min:3|max:64',
                'nickname' => 'required|max:64',
                'email'    => 'email|max:128',
                'password' => 'required|min:6|max:32',
            ]);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            $newId = $this->model->addUser($data);

            // 注册成功后直接签发 JWT，免二次登录
            $token = \utils\Auth::issue([
                'id'       => $newId,
                'username' => $data['username'],
                'role'     => 'user',
            ]);

            Response::json([
                'token' => $token,
                'user'  => ['id' => $newId, 'username' => $data['username'], 'nickname' => $data['nickname'], 'role' => 'user'],
            ], 0, '注册成功');
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 删除用户
     * POST /backend/user/delete
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
            $this->model->deleteUser($id);
            Response::json(['id' => $id], 0, '删除成功');
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 获取当前登录用户信息（含头像）
     * GET /backend/user/profile
     *
     * 通过 Authorization: Bearer <token> 解析当前用户。
     */
    public function profile()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                Response::json(null, 401, '未登录或登录已过期');
            }

            $info = $this->model->getUserById((int) $user['uid']);
            if (!$info) {
                Response::json(null, 1, '用户不存在');
            }

            // 确保返回头像地址（含相对路径约定，前端以 / 前缀访问）
            if (empty($info['avatar'])) {
                $info['avatar'] = 'view/backend/images/avatar/avatar-media.png';
            }

            Response::json($info);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }
}
