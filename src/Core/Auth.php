<?php
declare(strict_types=1);

namespace Tendari\Core;

final class Auth
{
    public static function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_set_cookie_params([
                'httponly' => true,
                'secure' => $secure,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        self::loadUser();
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return (bool)($_SESSION['user']['is_admin'] ?? false);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireUser(): void
    {
        if (!self::check()) {
            if (is_htmx()) {
                header('HX-Redirect: /login');
            } else {
                flash('error', 'Please log in first.');
                redirect('/login');
            }
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::check()) {
            redirect('/login');
            exit;
        }
        if (!self::isAdmin()) {
            http_response_code(403);
            render('pages/403', ['title' => 'Forbidden']);
            exit;
        }
    }

    public static function requireGuest(): void
    {
        if (self::check()) {
            redirect('/dashboard');
            exit;
        }
    }

    /** @return array{ok:bool,error:?string} */
    public static function register(string $email, string $password, string $name): array
    {
        $email = strtolower(trim($email));
        $exists = Db::one('SELECT id FROM users WHERE email = ? COLLATE NOCASE', [$email]);
        if ($exists) {
            return ['ok' => false, 'error' => 'An account with this email already exists.'];
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        Db::q(
            'INSERT INTO users (email, password_hash, name, timezone) VALUES (?,?,?,?)',
            [$email, $hash, trim($name), 'UTC']
        );
        $userId = Db::lastId();
        // email verification token (24h)
        $token = bin2hex(random_bytes(24));
        Db::q(
            'INSERT INTO email_tokens (user_id, token, purpose, expires_at) VALUES (?,?,?,datetime("now", "+1 day"))',
            [$userId, $token, 'verify_email']
        );
        $user = Db::one('SELECT * FROM users WHERE id = ?', [$userId]);
        self::startSession($user);
        // Send verification email (best effort)
        \Tendari\Services\Verification::sendEmail($user, $token);
        return ['ok' => true, 'error' => null];
    }

    public static function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        if (!RateLimiter::allow('login:' . self::ip() . ':' . $email, 5, 300)) {
            return ['ok' => false, 'error' => 'Too many attempts. Try again in a few minutes.'];
        }
        $user = Db::one('SELECT * FROM users WHERE email = ? COLLATE NOCASE', [$email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }
        if (!(int)$user['is_active']) {
            return ['ok' => false, 'error' => 'Account disabled.'];
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Db::q('UPDATE users SET password_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        Db::q('UPDATE users SET last_login_at = datetime(\'now\') WHERE id = ?', [$user['id']]);
        self::startSession($user);
        RateLimiter::clear('login:' . self::ip() . ':' . $email);
        return ['ok' => true, 'error' => null];
    }

    public static function logout(): void
    {
        $sid = session_id();
        if ($sid) {
            Db::q('DELETE FROM sessions WHERE id = ?', [$sid]);
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function startSession(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        } else {
            @session_start();
        }
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'is_admin' => (int)$user['is_admin'],
        ];
        Db::q(
            'INSERT OR REPLACE INTO sessions (id, user_id, ip, user_agent, expires_at)
             VALUES (?,?,?,?,datetime(\'now\', \'+14 days\'))',
            [session_id(), $user['id'], self::ip(), substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250)]
        );
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(): void
    {
        $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || $sent === '' || !hash_equals(self::csrfToken(), $sent)) {
            http_response_code(419);
            if (is_htmx()) {
                echo '<div class="notification is-danger">Session expired — please reload the page.</div>';
            } else {
                echo 'CSRF token mismatch. Reload the page and try again.';
            }
            exit;
        }
    }

    private static function loadUser(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || isset($_SESSION['user'])) {
            return;
        }
        $sid = session_id();
        if (!$sid) {
            return;
        }
        $row = Db::one(
            'SELECT u.* FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE s.id = ? AND s.expires_at > datetime(\'now\')',
            [$sid]
        );
        if ($row && (int)$row['is_active']) {
            $_SESSION['user'] = [
                'id' => (int)$row['id'],
                'email' => $row['email'],
                'name' => $row['name'],
                'is_admin' => (int)$row['is_admin'],
            ];
        }
    }

    public static function ip(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');
    }
}

/** Simple file-backed rate limiter (5 attempts / 5 min per key). */
final class RateLimiter
{
    public static function allow(string $key, int $max, int $windowSec): bool
    {
        $file = self::path($key);
        $now = time();
        $attempts = [];
        if (is_file($file)) {
            $attempts = json_decode((string)file_get_contents($file), true) ?: [];
        }
        $attempts = array_values(array_filter($attempts, fn($t) => is_int($t) && $t > $now - $windowSec));
        if (count($attempts) >= $max) {
            return false;
        }
        $attempts[] = $now;
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($attempts));
        return true;
    }

    public static function clear(string $key): void
    {
        $file = self::path($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function path(string $key): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/ratelimit/' . md5($key) . '.json';
    }
}
