<?php

namespace model;

use utils\Db;

/**
 * 商品模型
 *
 * 对接 te_goods 表（表结构见 database/migration.sql）：
 *   id, name, price, stock, description, img_url, status, created_at, updated_at
 *
 * 商品标签通过 \model\GoodsTagModel 管理（对接 te_goodstag 表）。
 * 通过 \utils\Db 连接类访问数据库（预处理防注入）。
 */
class GoodsModel
{
    /**
     * 商品标签模型
     */
    private $tagModel;

    public function __construct()
    {
        $this->tagModel = new GoodsTagModel();
    }

    /**
     * 获取数据库连接
     */
    private function db(): Db
    {
        return Db::instance();
    }

    /**
     * 分页查询商品列表
     *
     * @param int    $page      页码（从 1 开始）
     * @param int    $pageSize  每页条数
     * @param string $order     排序字段（默认 id desc）
     * @param string $keyword   可选关键字，模糊匹配 name
     * @return array ['total' => 总数, 'list' => 商品列表（含 tags）]
     */
    public function getGoodsList(int $page = 1, int $pageSize = 10, string $order = 'id DESC', string $keyword = ''): array
    {
        $db = $this->db();

        // 排序字段白名单，防止注入
        $allowed = [
            'id desc', 'id asc', 'name asc', 'name desc',
            'price asc', 'price desc', 'stock asc', 'stock desc',
        ];
        $orderLower = strtolower($order);
        $orderSql = in_array($orderLower, $allowed, true) ? $orderLower : 'id desc';

        $where = [];
        $bind = [];
        if ($keyword !== '') {
            $where[] = 'name LIKE ?';
            $bind[] = '%' . $keyword . '%';
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) $db->scalar("SELECT COUNT(*) FROM te_goods $whereSql", $bind);

        $offset = ($page - 1) * $pageSize;
        $list = $db->fetchAll(
            "SELECT id, name, price, stock, description, img_url, status, created_at, updated_at
             FROM te_goods $whereSql
             ORDER BY $orderSql
             LIMIT $pageSize OFFSET $offset",
            $bind
        );

        // 批量查询每个商品的标签
        $ids = array_column($list, 'id');
        $tagsMap = $this->tagModel->getTagsMap($ids);

        foreach ($list as &$g) {
            $g['tags'] = $tagsMap[$g['id']] ?? [];
        }
        unset($g);

        return ['total' => $total, 'list' => $list];
    }

    /**
     * 获取单个商品详情（含标签）
     *
     * @param int $id
     * @return array|null
     */
    public function getGoodsInfo(int $id): ?array
    {
        $db = $this->db();
        $goods = $db->fetch(
            "SELECT id, name, price, stock, description, img_url, status, created_at, updated_at
             FROM te_goods WHERE id = ?",
            [$id]
        );
        if (!$goods) {
            return null;
        }

        $goods['tags'] = $this->tagModel->getTagsByGoods($id);
        return $goods;
    }

    /**
     * 新增商品（含标签）
     *
     * @param array $data name, price, stock, description?, img_url?, status?, tags?
     * @return int 商品 ID
     * @throws \RuntimeException 参数非法
     */
    public function addGoods(array $data): int
    {
        $name  = trim($data['name'] ?? '');
        $price = (float) ($data['price'] ?? 0);

        if ($name === '' || $price <= 0) {
            throw new \RuntimeException('商品名称和价格不能为空');
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            $id = $db->insert('te_goods', [
                'name'        => $name,
                'price'       => $price,
                'stock'       => (int) ($data['stock'] ?? 0),
                'description' => $data['description'] ?? '',
                'img_url'     => $data['img_url'] ?? '',
                'status'      => (int) ($data['status'] ?? 1),
            ]);

            // 写入标签
            $this->tagModel->saveTags($id, $data['tags'] ?? []);

            $db->commit();
            return $id;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * 更新商品（含标签）
     *
     * @param int   $id
     * @param array $data name?, price?, stock?, description?, img_url?, status?, tags?
     * @return bool
     * @throws \RuntimeException 商品不存在
     */
    public function updateGoods(int $id, array $data): bool
    {
        $db = $this->db();
        if (!$this->getGoodsInfo($id)) {
            throw new \RuntimeException('商品不存在');
        }

        $db->beginTransaction();
        try {
            $update = [];
            if (isset($data['name']))        $update['name']        = $data['name'];
            if (isset($data['price']))       $update['price']       = (float) $data['price'];
            if (isset($data['stock']))       $update['stock']       = (int) $data['stock'];
            if (isset($data['description'])) $update['description'] = $data['description'];
            if (isset($data['img_url']))     $update['img_url']     = $data['img_url'];
            if (isset($data['status']))      $update['status']      = (int) $data['status'];

            if ($update) {
                $db->update('te_goods', $update, 'id = ?', [$id]);
            }

            // 更新标签（若传入 tags 键）
            if (array_key_exists('tags', $data)) {
                $this->tagModel->saveTags($id, $data['tags'] ?? []);
            }

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * 删除商品（级联删除其标签）
     *
     * @param int $id
     * @return bool
     */
    public function deleteGoods(int $id): bool
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $this->tagModel->deleteByGoods($id);
            $result = $db->delete('te_goods', 'id = ?', [$id]);
            $db->commit();
            return $result > 0;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
