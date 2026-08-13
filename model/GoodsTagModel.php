<?php

namespace model;

use utils\Db;

/**
 * 商品标签模型
 *
 * 对接 te_tags 表（表结构见 database/migration.sql）：
 *   id, gid, name, created_at   （gid 关联 te_goods.id）
 *
 * 通过 \utils\Db 连接类访问数据库（预处理防注入）。
 * 供 \model\GoodsModel 调用，用于管理商品的标签。
 */
class GoodsTagModel
{
    /**
     * 获取数据库连接
     */
    private function db(): Db
    {
        return Db::instance();
    }

    /**
     * 查询单个商品的标签
     *
     * @param int $gid 商品 ID
     * @return array 标签名数组
     */
    public function getTagsByGoods(int $gid): array
    {
        $rows = $this->db()->fetchAll(
            "SELECT name FROM te_tags WHERE gid = ? ORDER BY id ASC",
            [$gid]
        );
        return array_column($rows, 'name');
    }

    /**
     * 批量查询多个商品的标签
     *
     * @param array $gids 商品 ID 列表
     * @return array [gid => [tag, ...]]
     */
    public function getTagsMap(array $gids): array
    {
        if (!$gids) {
            return [];
        }
        $place = implode(',', array_fill(0, count($gids), '?'));
        $rows = $this->db()->fetchAll(
            "SELECT gid, name FROM te_tags WHERE gid IN ($place) ORDER BY id ASC",
            $gids
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['gid']][] = $row['name'];
        }
        return $map;
    }

    /**
     * 为商品写入一组标签（先清空再写入，保证与传入数组一致）
     *
     * @param int   $gid  商品 ID
     * @param array $tags 标签名数组
     * @return void
     */
    public function saveTags(int $gid, array $tags): void
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            // 清空该商品原有标签
            $db->delete('te_tags', 'gid = ?', [$gid]);

            // 逐个写入（去空、去重）
            $seen = [];
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if ($tag === '' || isset($seen[$tag])) {
                    continue;
                }
                $seen[$tag] = true;
                $db->insert('te_tags', ['gid' => $gid, 'name' => $tag]);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * 删除某个商品的全部标签（删除商品时级联清理）
     *
     * @param int $gid 商品 ID
     * @return void
     */
    public function deleteByGoods(int $gid): void
    {
        $this->db()->delete('te_tags', 'gid = ?', [$gid]);
    }
}
