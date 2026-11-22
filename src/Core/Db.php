<?php
declare(strict_types=1);

namespace Tendari\Core;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        $path = self::dbPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        self::$pdo = $pdo;
        return $pdo;
    }

    public static function dbPath(): string
    {
        $path = (string)Config::get('db.path', '');
        if ($path === '' || $path[0] !== '/') {
            $path = dirname(__DIR__, 2) . '/' . ($path !== '' ? $path : 'storage/app.sqlite');
        }
        return $path;
    }

    /** Run a query; returns PDOStatement. */
    public static function q(string $sql, array $params = []): \PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $v = self::q($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function lastId(): int
    {
        return (int)self::pdo()->lastInsertId();
    }

    /** Execute numbered migration files in storage/migrations that have not run yet. */
    public static function migrate(): array
    {
        self::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                name TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $applied = self::q('SELECT name FROM migrations')->fetchAll(\PDO::FETCH_COLUMN);
        $applied = array_flip($applied);
        $dir = self::migrationsDir();
        $files = is_dir($dir) ? glob($dir . '/*.sql') : [];
        sort($files);
        $ran = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }
            $sql = (string)file_get_contents($file);
            self::pdo()->exec($sql);
            self::q('INSERT INTO migrations (name) VALUES (?)', [$name]);
            $ran[] = $name;
        }
        if ($ran !== [] || !self::q('SELECT 1 FROM sources LIMIT 1')->fetch()) {
            \Tendari\Services\SourceCatalog::seed();
        }
        return $ran;
    }

    public static function migrationsDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/migrations';
    }
}
