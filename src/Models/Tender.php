<?php
declare(strict_types=1);

namespace Tendari\Models;

use Tendari\Core\Db;

class Tender
{
    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT t.*, s.name AS source_name, s.code AS source_code FROM tenders t
             JOIN sources s ON s.id = t.source_id WHERE t.id = ?',
            [$id]
        );
    }

    public static function aliases(int $tenderId): array
    {
        return Db::all(
            'SELECT a.*, s.name AS source_name FROM tender_aliases a
             JOIN sources s ON s.id = a.source_id WHERE a.tender_id = ? ORDER BY s.name',
            [$tenderId]
        );
    }

    public static function recent(int $limit = 12): array
    {
        return Db::all(
            'SELECT t.id, t.title, t.organization, t.countries, t.funding_type, t.deadline_at, t.status,
                    t.source_url, s.name AS source_name
             FROM tenders t JOIN sources s ON s.id = t.source_id
             WHERE t.status = \'open\'
             ORDER BY t.first_seen_at DESC, t.id DESC LIMIT ?',
            [$limit]
        );
    }
}
