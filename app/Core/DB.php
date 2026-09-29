<?php
namespace App\Core;

use PDO;
use PDOStatement;

class DB
{
    private static $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('db');
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function get(string $sql, array $params = [])
    {
        return self::query($sql, $params)->fetch();
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function count(string $sql, array $params = []): int
    {
        return (int) self::query($sql, $params)->fetchColumn();
    }

    public static function insert(string $table, array $data)
    {
        $cols = array_keys($data);
        $ph = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $ph) . ")";
        self::query($sql, $data);
        return self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $wparams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $c => $v) {
            $sets[] = "`{$c}` = :set_{$c}";
            $params["set_{$c}"] = $v;
        }
        $sql = "UPDATE `{$table}` SET " . implode(',', $sets) . " WHERE {$where}";
        return self::query($sql, $params + $wparams)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::query("DELETE FROM `{$table}` WHERE {$where}", $params)->rowCount();
    }

    public static function beginTransaction(): void { self::pdo()->beginTransaction(); }
    public static function commit(): void { self::pdo()->commit(); }
    public static function rollBack(): void { self::pdo()->rollBack(); }
}
