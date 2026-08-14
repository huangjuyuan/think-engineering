<?php

namespace controller\frontend;

use model\GoodsModel;
use utils\Response;

/**
 * 前端商品控制器
 *
 * 为前端展示页面提供商品数据接口：
 *   GET /frontend/goods/list  -> 商品列表（分页）
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
     * 商品列表（前端展示用）
     * GET /frontend/goods/list
     * 参数：page, page_size（默认每页 25，5x5 布局）, keyword（搜索关键词，模糊匹配商品名称）
     */
    public function list()
    {
        try {
            $page     = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 25)));
            $keyword  = trim($_GET['keyword'] ?? '');

            // 前端展示只返回上架商品（status=1）
            $result = $this->model->getGoodsList($page, $pageSize, 'id ASC', $keyword, true);

            // 只返回前端需要的字段
            $list = array_map(function ($g) {
                $img = $g['img_url'] ?? '';
                if ($img === '') {
                    $img = 'view/backend/images/goods/default.jpg';
                }
                // 统一加 / 前缀，前端可直接以相对项目根的 URL 访问
                $img = strpos($img, 'http') === 0 ? $img : '/' . ltrim($img, '/');

                return [
                    'id'          => $g['id'],
                    'name'        => $g['name'],
                    'desc'        => $g['description'] ?? '',
                    'img'         => $img,
                    'price'       => (float) $g['price'],
                    'stock'       => (int) $g['stock'],
                ];
            }, $result['list']);

            Response::json([
                'total' => $result['total'],
                'list'  => $list,
            ]);
        } catch (\Throwable $e) {
            Response::json(null, 1, $e->getMessage());
        }
    }
}
