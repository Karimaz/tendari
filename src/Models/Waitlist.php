<?php

namespace Tendari\Models;

use Tendari\Core\Db;

final class Waitlist
{
    /** Insert an email (idempotent). Returns 'added' or 'exists'. */
    public static function add(string $email, string $source = 'landing'): string
    {
        $existing = Db::one('SELECT id FROM waiting_list WHERE email = ?', [$email]);
        if ($existing !== null) {
            return 'exists';
        }
        Db::q('INSERT INTO waiting_list (email, source) VALUES (?, ?)', [$email, $source]);
        return 'added';
    }

    public static function count(): int
    {
        return (int)Db::scalar('SELECT COUNT(*) FROM waiting_list');
    }

    public static function latest(int $limit = 50): array
    {
        return Db::all('SELECT * FROM waiting_list ORDER BY id DESC LIMIT ' . $limit);
    }
}
