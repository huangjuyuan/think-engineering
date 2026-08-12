<?php

namespace utils;

use PDO;
use PDOException;
use PDOStatement;

/**
 * MySQL 连接类（基于 PDO，单例）
 *
 * 提供：
 *   - 读取 config/database.php 配置文件建立连接
 *   - 单例复用连接，避免重复连接
 *   - prepared statement 预处理，防 SQL 注入
 *   - 常用查询助手：query / execute / fetch / fetchAll / insert / update / delete
 *   - 事务支持：beginTransaction / commit / rollBack
 *
 * 自动加载约定：命名空间 utils + 类名 Db => utils/Db.php
 *
 * 用法示例：
 *   $db = \utils\Db::instance();
 *   $rows = $db->fetchAll('SELECT * FROM te_user WHERE status = ?', [1]);
 *   $id   = $db->insert('te_user', ['username' => 'akira', 'nickname' => '阿基拉']);
 */
class Db
{
    /**
     * 单例实例
     * @var Db|null
     */
    private static $instance = null;

    /**
     * 连接配置
     * @var array
     */
    private static $config = null;

    /**
     * PDO 连接
     * @var PDO|null
     */
    private $pdo;

    /**
     * 是否已开启事务
     * @var bool
     */
    private $inTransaction = false;

    /**
     * 私有构造器（单例）
     */
    private function __construct(array $config)
    {
        $this->connect($config);
    }

    /**
     * 禁止克隆
     */
    private function __clone()
    {
    }

    /**
     * 获取单例实例
     *
     * @return Db
     */
    public static function instance(): Db
    {
        if (self::$instance === null) {
            $config = self::getConfig();
            self::$instance = new self($config['default']);
        }
        return self::$instance;
    }

    /**
     * 手动设置连接配置（可在实例化前覆盖默认配置）
     *
     * @param array $config 配置数组，结构同 config/database.php
     * @return void
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    /**
     * 获取连接配置
     * 优先使用 setConfig 设置的配置，否则统一经 \utils\Config 读取 config/database.yaml（YAML 格式）。
     *
     * @return array
     */
    private static function getConfig(): array
    {
        if (self::$config === null) {
            self::$config = Config::get('database');
            if (empty(self::$config['default'])) {
                self::$config = [
                    'default' => [
                        'host' => '127.0.0.1', 'port' => 3306,
                        'database' => 'think_engineering',
                        'username' => 'root', 'password' => '',
                        'charset' => 'utf8mb4', 'prefix' => 'te_',
                    ],
                ];
            }
        }
        return self::$config;
    }

    /**
     * 建立 PDO 连接
     *
     * @param array $config
     * @return void
     * @throws PDOException 连接失败时抛出
     */
    private function connect(array $config): void
    {
        $host     = $config['host']     ?? '127.0.0.1';
        $port     = $config['port']     ?? 3306;
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $charset  = $config['charset']  ?? 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 抛异常
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 关联数组
            PDO::ATTR_EMULATE_PREPARES   => false,                  // 使用原生预处理
            PDO::ATTR_PERSISTENT         => false,                  // 不常驻连接（便于开发调试）
        ];

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // 针对 MySQL 8 caching_sha2_password 认证兼容性问题给出友好提示
            if (strpos($msg, 'caching_sha2_password') !== false || strpos($msg, '2054') !== false) {
                throw new PDOException(
                    "数据库连接失败：MySQL 8 使用了 caching_sha2_password 认证，"
                    . "当前 PHP " . PHP_VERSION . " 的 pdo_mysql 不支持。\n"
                    . "解决办法（任选其一）：\n"
                    . "  1) 将该用户改为 mysql_native_password 认证：\n"
                    . "     ALTER USER '{$username}'@'%' IDENTIFIED WITH mysql_native_password BY '{$password}';\n"
                    . "  2) 或创建使用 mysql_native_password 的新数据库用户。\n"
                    . "原始错误：{$msg}",
                    (int) $e->getCode()
                );
            }
            throw $e;
        }
    }

    /**
     * 返回底层 PDO 对象（需要原生 PDO 能力时使用）
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * 执行 SQL 并返回受影响行数（用于 INSERT / UPDATE / DELETE / DDL）
     *
     * @param string $sql  SQL 语句，参数用 ? 占位
     * @param array  $bind 绑定参数
     * @return int 受影响行数
     */
    public function execute(string $sql, array $bind = []): int
    {
        $stmt = $this->prepare($sql, $bind);
        return $stmt->rowCount();
    }

    /**
     * 查询单行
     *
     * @param string $sql
     * @param array  $bind
     * @return array|null 有数据返回关联数组，无数据返回 null
     */
    public function fetch(string $sql, array $bind = []): ?array
    {
        $stmt = $this->prepare($sql, $bind);
        $row  = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * 查询多行
     *
     * @param string $sql
     * @param array  $bind
     * @return array
     */
    public function fetchAll(string $sql, array $bind = []): array
    {
        $stmt = $this->prepare($sql, $bind);
        return $stmt->fetchAll();
    }

    /**
     * 获取单个标量值（如 COUNT(*)、MAX(id)）
     *
     * @param string $sql
     * @param array  $bind
     * @return mixed
     */
    public function scalar(string $sql, array $bind = [])
    {
        $stmt = $this->prepare($sql, $bind);
        return $stmt->fetchColumn();
    }

    /**
     * 插入数据
     *
     * @param string $table 表名（不含前缀；若配置了 prefix 会自动拼接）
     * @param array  $data  字段 => 值
     * @return int 自增主键 ID（无自增主键返回 0）
     */
    public function insert(string $table, array $data): int
    {
        $table = $this->withPrefix($table);
        $cols  = array_keys($data);
        $place = array_fill(0, count($cols), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $cols),
            implode(', ', $place)
        );

        $this->execute($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * 更新数据
     *
     * @param string $table  表名
     * @param array  $data   字段 => 值
     * @param string $where  WHERE 条件（? 占位）
     * @param array  $bind   WHERE 绑定参数
     * @return int 受影响行数
     */
    public function update(string $table, array $data, string $where, array $bind = []): int
    {
        $table = $this->withPrefix($table);
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "`$col` = ?";
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $set),
            $where
        );

        $params = array_merge(array_values($data), $bind);
        return $this->execute($sql, $params);
    }

    /**
     * 删除数据
     *
     * @param string $table 表名
     * @param string $where WHERE 条件
     * @param array  $bind  绑定参数
     * @return int 受影响行数
     */
    public function delete(string $table, string $where, array $bind = []): int
    {
        $table = $this->withPrefix($table);
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        return $this->execute($sql, $bind);
    }

    /**
     * 开启事务
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        if (!$this->inTransaction) {
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
        }
    }

    /**
     * 提交事务
     *
     * @return void
     */
    public function commit(): void
    {
        if ($this->inTransaction) {
            $this->pdo->commit();
            $this->inTransaction = false;
        }
    }

    /**
     * 回滚事务
     *
     * @return void
     */
    public function rollBack(): void
    {
        if ($this->inTransaction) {
            $this->pdo->rollBack();
            $this->inTransaction = false;
        }
    }

    /**
     * 是否处于事务中
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    /**
     * 最后插入的自增 ID
     *
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * 表前缀拼接
     *
     * @param string $table
     * @return string
     */
    private function withPrefix(string $table): string
    {
        $prefix = self::getConfig()['default']['prefix'] ?? '';
        if ($prefix !== '' && strpos($table, $prefix) !== 0) {
            return $prefix . $table;
        }
        return $table;
    }

    /**
     * 预处理 SQL 并绑定参数
     *
     * @param string $sql
     * @param array  $bind
     * @return PDOStatement
     */
    private function prepare(string $sql, array $bind): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($bind as $index => $value) {
            // 占位符从 1 开始编号（PDO 原生预处理）
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->execute();
        return $stmt;
    }
}
