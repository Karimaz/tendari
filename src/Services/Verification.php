<?php
declare(strict_types=1);

namespace Tendari\Services;

use Tendari\Core\Db;
use Tendari\Models\User;

/** Email verification + Telegram link token helpers. */
final class Verification
{
    public static function sendEmail(array $user, string $token): void
    {
        $link = url('/verify-email?token=' . $token);
        try {
            (new \Tendari\Notifiers\EmailNotifier())->sendSimple(
                (string)$user['email'],
                'Verify your Tendari email',
                '<p>Welcome to Tendari, ' . e($user['name'] ?: $user['email']) . '!</p>' .
                '<p><a href="' . e($link) . '">Click here to verify your email address</a>.</p>' .
                '<p>Until verified, email notifications stay off. Link valid for 24 hours.</p>'
            );
        } catch (\Throwable $e) {
            logger('warning', 'Verification email failed: ' . $e->getMessage());
        }
    }

    public static function verifyToken(string $token): ?array
    {
        $row = Db::one(
            "SELECT * FROM email_tokens WHERE token = ? AND purpose = 'verify_email' AND expires_at > datetime('now')",
            [$token]
        );
        if ($row === null) {
            return null;
        }
        Db::q('UPDATE users SET email_verified = 1 WHERE id = ?', [$row['user_id']]);
        Db::q('DELETE FROM email_tokens WHERE id = ?', [$row['id']]);
        return User::find((int)$row['user_id']);
    }

    /** One-time token for Telegram /start deep link. */
    public static function telegramLinkToken(int $userId): string
    {
        Db::q('DELETE FROM telegram_link_tokens WHERE user_id = ?', [$userId]);
        $token = bin2hex(random_bytes(8));
        Db::q('INSERT INTO telegram_link_tokens (token, user_id) VALUES (?,?)', [$token, $userId]);
        return $token;
    }

    public static function consumeTelegramToken(string $token): ?array
    {
        $row = Db::one(
            "SELECT * FROM telegram_link_tokens WHERE token = ? AND created_at > datetime('now', '-1 day')",
            [$token]
        );
        if ($row === null) {
            return null;
        }
        Db::q('DELETE FROM telegram_link_tokens WHERE token = ?', [$token]);
        return User::find((int)$row['user_id']);
    }
}
