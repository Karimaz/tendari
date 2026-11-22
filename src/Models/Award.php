<?php
declare(strict_types=1);

namespace Tendari\Models;

use Tendari\Core\Db;

class Award
{
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT a.*, s.name AS source_name, s.code AS source_code FROM awards a
             JOIN sources s ON s.id = a.source_id WHERE a.id = ?',
            [$id]
        );
    }

    public static function forTender(int $tenderId, int $limit = 10): array
    {
        return Db::all(
            'SELECT a.*, s.name AS source_name FROM awards a
             JOIN sources s ON s.id = a.source_id
             WHERE a.tender_id = ? ORDER BY a.awarded_at DESC, a.id DESC LIMIT ?',
            [$tenderId, $limit]
        );
    }

    public static function recent(int $limit = 20): array
    {
        return Db::all(
            'SELECT a.*, s.name AS source_name FROM awards a
             JOIN sources s ON s.id = a.source_id
             ORDER BY a.awarded_at DESC, a.id DESC LIMIT ?',
            [$limit]
        );
    }

    public static function count(): int
    {
        return (int)Db::scalar('SELECT COUNT(*) FROM awards');
    }
}
