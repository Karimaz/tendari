<?php
declare(strict_types=1);

namespace Tendari\Models;

use Tendari\Core\Db;

class User
{
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function byEmail(string $email): ?array
    {
        return Db::one('SELECT * FROM users WHERE email = ? COLLATE NOCASE', [strtolower(trim($email))]);
    }

    public static function all(): array
    {
        return Db::all('SELECT * FROM users ORDER BY created_at DESC');
    }

    public static function setActive(int $id, bool $active): void
    {
        Db::q('UPDATE users SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    public static function setAdmin(int $id, bool $admin): void
    {
        Db::q('UPDATE users SET is_admin = ? WHERE id = ?', [$admin ? 1 : 0, $id]);
    }

    public static function updateProfile(int $id, array $fields): void
    {
        $allowed = ['name', 'email_notifications', 'notify_digest', 'timezone', 'password_hash',
                    'telegram_chat_id', 'telegram_verified', 'email_verified'];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            Db::q("UPDATE users SET {$key} = ? WHERE id = ?", [$value, $id]);
        }
    }

    public static function linkTelegram(int $id, string $chatId): void
    {
        Db::q('UPDATE users SET telegram_chat_id = ?, telegram_verified = 1 WHERE id = ?', [$chatId, $id]);
    }

    public static function unlinkTelegram(int $id): void
    {
        Db::q('UPDATE users SET telegram_chat_id = NULL, telegram_verified = 0 WHERE id = ?', [$id]);
    }

    public static function notifyChannels(array $user): array
    {
        $channels = [];
        if ((int)$user['email_notifications'] === 1 && (int)$user['email_verified'] === 1
            && (string)config('smtp.host') !== '') {
            $channels[] = 'email';
        }
        if ((int)$user['telegram_verified'] === 1 && (string)config('telegram.bot_token') !== '') {
            $channels[] = 'telegram';
        }
        return $channels;
    }
}
