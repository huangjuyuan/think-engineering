<?php

namespace controller\backend;

use model\GoodsModel;
use utils\Response;
use utils\Validator;

/**
 * 商品控制器
 *
 * 提供 JSON API：
 *   GET  /backend/goods/list     -> 商品列表（分页/搜索，含标签）
 *   GET  /backend/goods/get?id=N -> 获取单个商品（含标签）
 *   POST /backend/goods/save     -> 新增/更新商品（含标签）
 *   POST /backend/goods/delete   -> 删除商品
 *
 * 数据通过 \model\GoodsModel 对接数据库 te_goods / te_tags 表。
 */
class GoodsController
{
    /**
     * 商品模型
     */
    private $model;

    public function __construct()
    {
        $this->model = new GoodsModel();
    }

    /**
     * 商品列表
     * GET /backend/goods/list
     * 参数：page, page_size, order, keyword
     */
    public function list()
    {
        try {
            $errors = Validator::check($_GET, [
                'page'      => 'integer|min:1',
                'page_size' => 'integer|between:1,100',
                'order'     => 'in:id DESC,id ASC,name ASC,name DESC,price ASC,price DESC,stock ASC,stock DESC',
            ]);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            $page     = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 10)));
            $order    = trim($_GET['order'] ?? 'id DESC');
            $keyword  = trim($_GET['keyword'] ?? '');

            $result = $this->model->getGoodsList($page, $pageSize, $order, $keyword);
            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 获取单个商品（编辑页回填，含标签）
     * GET /backend/goods/get?id=1
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

            $id    = intval($_GET['id']);
            $goods = $this->model->getGoodsInfo($id);
            if (!$goods) {
                Response::json(null, 1, '商品不存在');
            }
            Response::json($goods);
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
     * 新增 / 更新商品
     * POST /backend/goods/save
     * 字段：id(编辑时), name, price, stock, status, tags(逗号分隔), description, img_url
     */
    public function save()
    {
        try {
            $id   = intval($_POST['id'] ?? 0);
            $data = [
                'name'        => trim($_POST['name'] ?? ''),
                'price'       => floatval($_POST['price'] ?? 0),
                'stock'       => intval($_POST['stock'] ?? 0),
                'status'      => intval($_POST['status'] ?? 1),
                'description' => trim($_POST['description'] ?? ''),
                'img_url'     => trim($_POST['img_url'] ?? ''),
            ];

            // 逗号分隔的标签 -> 数组
            $tagsStr = $_POST['tags'] ?? '';
            $data['tags'] = $tagsStr !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $tagsStr))))
                : [];

            $errors = Validator::check($data, [
                'name'    => 'required|max:128',
                'price'   => 'required|number|min:0.01',
                'stock'   => 'integer|min:0',
                'status'  => 'integer|between:0,1',
                'img_url' => 'url|max:128',
            ]);
            if ($errors) {
                Response::json($errors, 1, '参数校验失败');
            }

            if ($id > 0) {
                $this->model->updateGoods($id, $data);
                Response::json(['id' => $id], 0, '更新成功');
            } else {
                $newId = $this->model->addGoods($data);
                Response::json(['id' => $newId], 0, '新增成功');
            }
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }

    /**
     * 删除商品
     * POST /backend/goods/delete
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
            $this->model->deleteGoods($id);
            Response::json(['id' => $id], 0, '删除成功');
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }
}
