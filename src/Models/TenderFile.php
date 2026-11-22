<?php

namespace Tendari\Models;

use Tendari\Core\Db;

final class TenderFile
{
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM tender_files WHERE id = ?', [$id]);
    }

    /** Ready files for the detail page; page snapshots last. */
    public static function forTender(int $tenderId): array
    {
        return Db::all(
            "SELECT * FROM tender_files WHERE tender_id = ? AND status = 'ready'
             ORDER BY CASE kind WHEN 'document' THEN 0 ELSE 1 END, id",
            [$tenderId]
        );
    }

    public static function pending(int $tenderId, int $limit = 10): array
    {
        return Db::all(
            "SELECT * FROM tender_files WHERE tender_id = ? AND status = 'pending' ORDER BY id LIMIT ?",
            [$tenderId, $limit]
        );
    }

    public static function globalPending(int $limit = 50): array
    {
        return Db::all(
            "SELECT * FROM tender_files WHERE status = 'pending' AND created_at >= datetime('now', '-14 days') ORDER BY id LIMIT ?",
            [$limit]
        );
    }

    public static function hasReady(int $tenderId): bool
    {
        return (int)Db::scalar(
            "SELECT COUNT(*) FROM tender_files WHERE tender_id = ? AND status = 'ready'",
            [$tenderId]
        ) > 0;
    }

    public static function countForTender(int $tenderId): int
    {
        return (int)Db::scalar('SELECT COUNT(*) FROM tender_files WHERE tender_id = ?', [$tenderId]);
    }

    /** Queue a document URL. Returns true when newly inserted. */
    public static function add(int $tenderId, string $url, ?string $label, string $kind = 'document'): bool
    {
        if ($url === '') {
            return false;
        }
        $st = Db::q(
            'INSERT OR IGNORE INTO tender_files (tender_id, url, label, kind) VALUES (?,?,?,?)',
            [$tenderId, $url, $label !== null ? mb_substr($label, 0, 190) : null, $kind]
        );
        return $st->rowCount() > 0;
    }

    public static function markReady(int $id, array $data): void
    {
        Db::q(
            "UPDATE tender_files SET status = 'ready', mime = ?, filename = ?, size = ?, sha256 = ?,
                    storage_path = ?, error = NULL, fetched_at = datetime('now') WHERE id = ?",
            [
                $data['mime'] ?? null,
                $data['filename'] ?? null,
                $data['size'] ?? null,
                $data['sha256'] ?? null,
                $data['storage_path'] ?? null,
                $id,
            ]
        );
    }

    public static function markFailed(int $id, string $error): void
    {
        Db::q(
            "UPDATE tender_files SET status = 'failed', error = ?, fetched_at = datetime('now') WHERE id = ?",
            [mb_substr($error, 0, 300), $id]
        );
    }
}
