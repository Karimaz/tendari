<?php
declare(strict_types=1);

namespace Tendari\Models;

use Tendari\Core\Db;

class Notification
{
    public static function exists(int $userId, int $tenderId, string $channel): bool
    {
        return (bool)Db::one(
            'SELECT id FROM notifications WHERE user_id = ? AND tender_id = ? AND channel = ?',
            [$userId, $tenderId, $channel]
        );
    }

    public static function create(int $userId, int $tenderId, string $channel): int
    {
        Db::q(
            'INSERT OR IGNORE INTO notifications (user_id, tender_id, channel) VALUES (?,?,?)',
            [$userId, $tenderId, $channel]
        );
        return Db::lastId();
    }

    public static function pending(int $limit = 500): array
    {
        return Db::all(
            "SELECT n.*, u.email, u.telegram_chat_id, u.notify_digest, u.timezone, u.email_verified
             FROM notifications n JOIN users u ON u.id = n.user_id
             WHERE n.status = 'pending' AND n.attempts < 3
             ORDER BY n.id LIMIT ?",
            [$limit]
        );
    }

    public static function markSent(int $id): void
    {
        Db::q("UPDATE notifications SET status = 'sent', sent_at = datetime('now'), error = NULL WHERE id = ?", [$id]);
    }

    public static function markFailed(int $id, string $error): void
    {
        Db::q(
            "UPDATE notifications SET attempts = attempts + 1,
             status = CASE WHEN attempts + 1 >= 3 THEN 'failed' ELSE 'pending' END,
             error = ? WHERE id = ?",
            [$error, $id]
        );
    }

    public static function forUser(int $userId, int $limit = 100): array
    {
        return Db::all(
            'SELECT n.*, t.title AS tender_title, t.source_url, t.deadline_at
             FROM notifications n JOIN tenders t ON t.id = n.tender_id
             WHERE n.user_id = ? ORDER BY n.id DESC LIMIT ?',
            [$userId, $limit]
        );
    }
}
