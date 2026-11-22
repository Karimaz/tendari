<?php
/**
 * Global helper functions used by controllers, templates and CLI scripts.
 */
declare(strict_types=1);

use Tendari\Core\Auth;
use Tendari\Core\Config;
use Tendari\Core\Db;
use Tendari\Core\I18n;

function config(string $path, mixed $default = null): mixed
{
    return Config::get($path, $default);
}

/** Translate a UI string for the active locale. */
function t(string $key, array $params = [], ?string $default = null): string
{
    return I18n::t($key, $params, $default);
}

function locale(): string
{
    return I18n::locale();
}

function locale_dir(): string
{
    return I18n::dir();
}

function db(): PDO
{
    return Db::pdo();
}

/** Escape all output. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function is_htmx(): bool
{
    return ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function keep_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

/** Render a template inside the layout (or standalone for HTMX partials). */
function render(string $template, array $vars = [], ?string $layout = 'layout'): void
{
    extract($vars, EXTR_SKIP);
    if (is_htmx()) {
        render_partial($template, $vars);
        return;
    }
    if ($layout !== null) {
        ob_start();
        render_partial($template, $vars);
        $content = (string)ob_get_clean();
        $title = $title ?? 'Tendari';
        render_partial($layout, array_merge($vars, ['content' => $content, 'title' => $title]));
        return;
    }
    render_partial($template, $vars);
}

function render_partial(string $template, array $vars = []): void
{
    $file = dirname(__DIR__) . '/templates/' . $template . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Template not found: ' . $template);
    }
    extract($vars, EXTR_SKIP);
    include $file;
}

/** URL helper: base_url + path. */
function url(string $path = ''): string
{
    return rtrim((string)config('app.base_url', 'http://localhost:8080'), '/') . $path;
}

/** JSON list stored in a TEXT column (countries, sectors, ...). */
function json_list(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_filter(array_map('strval', $value), fn($v) => $v !== ''));
    }
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('strval', $decoded), fn($v) => $v !== ''));
    }
    return array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $value) ?: []), fn($v) => $v !== ''));
}

function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

/** Daily log file in storage/logs/YYYY-MM-DD.log */
function logger(string $level, string $message, array $context = []): void
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = sprintf(
        "[%s] %s: %s %s\n",
        now_utc(),
        $level,
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
    );
    @file_put_contents($dir . '/' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND);
}

/** human friendly "in 3 days" / "5 days ago" */
/** Human-readable file size (e.g. 1.4 MB). */
function format_bytes(?int $bytes): string
{
    if ($bytes === null || $bytes <= 0) {
        return '—';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    $value = $bytes / (1024 ** $i);
    return ($i === 0 ? (string)(int)$value : number_format($value, $value >= 10 ? 0 : 1)) . ' ' . $units[$i];
}

function time_diff(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    $diff = $ts - time();
    $abs = abs($diff);
    $days = (int)round($abs / 86400);
    $hours = (int)round($abs / 3600);
    $label = $days >= 1 ? $days . ' day' . ($days === 1 ? '' : 's') : $hours . ' hour' . ($hours === 1 ? '' : 's');
    return $diff >= 0 ? 'in ' . $label : $label . ' ago';
}
