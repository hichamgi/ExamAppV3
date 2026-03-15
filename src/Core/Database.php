<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = Config::get('database');

        if (!is_array($config)) {
            throw new PDOException('Configuration database introuvable.');
        }

        $driver = (string) ($config['driver'] ?? 'mysql');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];

        if ($database === '') {
            throw new PDOException('Nom de base de données vide.');
        }

        $dsn = match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host,
                $port,
                $database,
                $charset
            ),
            default => throw new PDOException('Driver DB non supporté : ' . $driver),
        };

        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new PDO($dsn, $username, $password, $options + $defaultOptions);
    }

    public static function instance(): self
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function pdo(): PDO
    {
        return self::instance()->pdo;
    }

    public static function reconnect(): void
    {
        self::$instance = null;
    }

    public static function beginTransaction(): bool
    {
        return self::pdo()->beginTransaction();
    }

    public static function commit(): bool
    {
        if (!self::pdo()->inTransaction()) {
            return false;
        }

        return self::pdo()->commit();
    }

    public static function rollBack(): bool
    {
        if (!self::pdo()->inTransaction()) {
            return false;
        }

        return self::pdo()->rollBack();
    }

    public static function inTransaction(): bool
    {
        return self::pdo()->inTransaction();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedHere = true;
            } else {
                $startedHere = false;
            }

            $result = $callback($pdo);

            if ($startedHere && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if (($startedHere ?? false) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function statement(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::normalizeParams($params));

        return $stmt;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        return self::statement($sql, $params);
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::statement($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $rows = self::statement($sql, $params)->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function fetchValue(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = self::statement($sql, $params)->fetchColumn();

        return $value === false ? $default : $value;
    }

    public static function exists(string $sql, array $params = []): bool
    {
        return self::fetchValue($sql, $params) !== null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::statement($sql, $params);

        return $stmt->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        if ($table === '' || $data === []) {
            throw new PDOException('Insert invalide.');
        }

        $columns = array_keys($data);
        $placeholders = array_map(
            static fn(string $column): string => ':' . $column,
            $columns
        );

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn(string $c): string => "`{$c}`", $columns)),
            implode(', ', $placeholders)
        );

        self::statement($sql, $data);

        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if ($table === '' || $data === [] || trim($where) === '') {
            throw new PDOException('Update invalide.');
        }

        $setParts = [];
        $params = [];

        foreach ($data as $column => $value) {
            $param = 'set_' . $column;
            $setParts[] = "`{$column}` = :{$param}";
            $params[$param] = $value;
        }

        foreach ($whereParams as $key => $value) {
            $params[$key] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            $where
        );

        return self::execute($sql, $params);
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        if ($table === '' || trim($where) === '') {
            throw new PDOException('Delete invalide.');
        }

        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);

        return self::execute($sql, $params);
    }

    public static function paginate(
        string $sql,
        array $params = [],
        int $page = 1,
        int $perPage = 20
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset = ($page - 1) * $perPage;

        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS subquery_count';
        $total = (int) self::fetchValue($countSql, $params, 0);

        $pagedSql = $sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $items = self::fetchAll($pagedSql, $params);

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    private static function normalizeParams(array $params): array
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}