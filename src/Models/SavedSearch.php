<?php
declare(strict_types=1);

namespace Tendari\Models;

use Tendari\Core\Db;

class SavedSearch
{
    public static function forUser(int $userId): array
    {
        return Db::all('SELECT * FROM saved_searches WHERE user_id = ? ORDER BY created_at DESC', [$userId]);
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM saved_searches WHERE id = ?', [$id]);
    }

    public static function forUserOr404(int $id, int $userId): ?array
    {
        $s = self::find($id);
        return ($s && (int)$s['user_id'] === $userId) ? $s : null;
    }

    public static function active(): array
    {
        return Db::all(
            'SELECT ss.*, u.email, u.email_notifications, u.email_verified, u.telegram_chat_id,
                    u.telegram_verified, u.notify_digest, u.is_active AS user_active, u.timezone
             FROM saved_searches ss JOIN users u ON u.id = ss.user_id
             WHERE ss.active = 1 AND u.is_active = 1'
        );
    }

    public static function create(int $userId, array $data): int
    {
        Db::q(
            'INSERT INTO saved_searches (user_id, name, keywords, exclude_keywords, countries, sectors,
                                        funding_types, deadline_within_days, active)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $userId,
                $data['name'],
                $data['keywords'] ?? '',
                $data['exclude_keywords'] ?? '',
                json_encode(json_list($data['countries'] ?? [])),
                json_encode(json_list($data['sectors'] ?? [])),
                json_encode(json_list($data['funding_types'] ?? [])),
                $data['deadline_within_days'] !== '' && $data['deadline_within_days'] !== null
                    ? (int)$data['deadline_within_days'] : null,
                !empty($data['active']) ? 1 : 0,
            ]
        );
        return Db::lastId();
    }

    public static function update(int $id, array $data): void
    {
        Db::q(
            'UPDATE saved_searches SET name = ?, keywords = ?, exclude_keywords = ?, countries = ?, sectors = ?,
                    funding_types = ?, deadline_within_days = ?, active = ? WHERE id = ?',
            [
                $data['name'],
                $data['keywords'] ?? '',
                $data['exclude_keywords'] ?? '',
                json_encode(json_list($data['countries'] ?? [])),
                json_encode(json_list($data['sectors'] ?? [])),
                json_encode(json_list($data['funding_types'] ?? [])),
                $data['deadline_within_days'] !== '' && $data['deadline_within_days'] !== null
                    ? (int)$data['deadline_within_days'] : null,
                !empty($data['active']) ? 1 : 0,
                $id,
            ]
        );
    }

    public static function toggleActive(int $id): void
    {
        Db::q('UPDATE saved_searches SET active = 1 - active WHERE id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Db::q('DELETE FROM saved_searches WHERE id = ?', [$id]);
    }
}
