<?php
declare(strict_types=1);

namespace Tendari\Models;

use Tendari\Core\Db;

class Source
{
    public static function all(): array
    {
        return Db::all('SELECT * FROM sources ORDER BY name');
    }

    public static function byCode(string $code): ?array
    {
        return Db::one('SELECT * FROM sources WHERE code = ?', [$code]);
    }

    public static function enabled(): array
    {
        return Db::all('SELECT * FROM sources WHERE enabled = 1 ORDER BY id');
    }

    public static function setEnabled(int $id, bool $on): void
    {
        Db::q('UPDATE sources SET enabled = ? WHERE id = ?', [$on ? 1 : 0, $id]);
    }

    public static function setStatus(int $id, string $status, ?string $error = null): void
    {
        Db::q(
            'UPDATE sources SET last_status = ?, last_error = ?, last_fetched_at = datetime(\'now\') WHERE id = ?',
            [$status, $error, $id]
        );
    }

    public static function due(): array
    {
        return Db::all(
            "SELECT * FROM sources WHERE enabled = 1 AND
             (last_fetched_at IS NULL OR
              last_fetched_at < datetime('now', '-' || fetch_interval_min || ' minutes'))"
        );
    }

    public static function insert(array $row): int
    {
        Db::q(
            'INSERT OR IGNORE INTO sources (code, name, region, connector_class, enabled, fetch_interval_min, config)
             VALUES (?,?,?,?,?,?,?)',
            [
                $row['code'], $row['name'], $row['region'] ?? null, $row['connector_class'],
                $row['enabled'] ?? 0, $row['fetch_interval_min'] ?? 360,
                isset($row['config']) ? json_encode($row['config']) : null,
            ]
        );
        return Db::lastId();
    }
}
