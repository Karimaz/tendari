<?php
declare(strict_types=1);

namespace Tendari\Services;

use Tendari\Connectors\AbstractConnector;
use Tendari\Connectors\HttpClient;
use Tendari\Core\Db;

/**
 * Live endpoint health checks: probes each source's probeUrl() without running
 * a full fetch, classifies the result (ok / down / changed / stub) and stores
 * the last report in app_state so the admin UI + cron can read it.
 */
final class HealthChecker
{
    public const STATE_KEY = 'health.probes';

    /** @return array<array{source:string,url:?string,verdict:string,message:string,http_code:?int,latency_ms:?int}> */
    public static function probeAll(?array $sources = null): array
    {
        $sources = $sources ?? \Tendari\Models\Source::all();
        $results = [];
        foreach ($sources as $source) {
            $probe = self::probeSource($source);
            $results[(string)$source['code']] = $probe;
            if (in_array($probe['verdict'], ['down', 'changed', 'error'], true)) {
                logger('warning', 'Health check problem', [
                    'source' => $probe['source'],
                    'verdict' => $probe['verdict'],
                    'message' => $probe['message'],
                ]);
            }
        }
        Db::q(
            'INSERT INTO app_state (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            [self::STATE_KEY, json_encode(['checked_at' => now_utc(), 'results' => $results])]
        );
        return $results;
    }

    public static function probeSource(array $source): array
    {
        $class = (string)$source['connector_class'];
        if (!class_exists($class)) {
            return self::result($source, 'error', 'Connector class not found: ' . $class);
        }
        $config = json_decode((string)($source['config'] ?? ''), true) ?: [];
        try {
            $conn = new $class($config, (string)$source['code']);
        } catch (\Throwable $e) {
            return self::result($source, 'error', 'Connector init failed: ' . $e->getMessage());
        }

        if (!$conn instanceof AbstractConnector) {
            return self::result($source, 'error', 'Not a connector: ' . $class);
        }

        $url = $conn->probeUrl();
        if ($url === null) {
            $verdict = str_contains($class, 'RegistrationConnector') ? 'needs-credentials' : 'stub';
            return self::result($source, $verdict, 'No probe URL (stub connector)');
        }

        $meta = HttpClient::probe($url);
        if ($meta === null) {
            return self::result($source, 'down', 'Connection failed / timeout', $url);
        }
        $verdict = self::evaluate($meta['code'], $meta['body'], $conn->expectJson());
        return self::result(
            $source,
            $verdict['status'],
            $verdict['message'],
            $url,
            $meta['code'],
            $meta['latency_ms']
        );
    }

    /** Pure classification of a probe response. */
    public static function evaluate(int $httpCode, string $body, bool $expectJson): array
    {
        if ($httpCode === 405) {
            return ['status' => 'ok', 'message' => 'Endpoint up (HTTP method restricted to POST)'];
        }
        if ($httpCode >= 400) {
            return ['status' => 'down', 'message' => 'HTTP ' . $httpCode . ' — endpoint down or blocked: '
                . mb_substr(trim(strip_tags($body)), 0, 120)];
        }
        if (trim($body) === '') {
            return ['status' => 'down', 'message' => 'Empty response body'];
        }
        if ($expectJson) {
            $trimmed = ltrim($body);
            $first = $trimmed !== '' ? $trimmed[0] : '';
            if ($first !== '{' && $first !== '[') {
                return ['status' => 'changed', 'message' => 'HTTP 200 but body is not JSON — API may have moved or changed: '
                    . mb_substr(trim(strip_tags($body)), 0, 120)];
            }
            // Note: body may be truncated by the probe (4KB head); parse what we can.
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $status = $decoded['status'] ?? null;
                if ((is_int($status) && $status >= 400)
                    || (is_string($status) && in_array(strtolower($status), ['error', 'fail', 'failed'], true))) {
                    return ['status' => 'changed', 'message' => 'API returned an error document: '
                        . mb_substr((string)($decoded['error']['message'] ?? $decoded['message'] ?? 'unknown'), 0, 120)];
                }
            }
            // truncated but JSON-shaped -> assume healthy
        }
        return ['status' => 'ok', 'message' => 'Endpoint healthy'];
    }

    public static function stored(): ?array
    {
        $raw = Db::scalar('SELECT value FROM app_state WHERE key = ?', [self::STATE_KEY]);
        if ($raw === null) {
            return null;
        }
        return json_decode((string)$raw, true) ?: null;
    }

    private static function result(
        array $source,
        string $verdict,
        string $message,
        ?string $url = null,
        ?int $httpCode = null,
        ?int $latency = null
    ): array {
        return [
            'source' => (string)$source['code'],
            'url' => $url,
            'verdict' => $verdict,
            'message' => $message,
            'http_code' => $httpCode,
            'latency_ms' => $latency,
        ];
    }

    /** Go-live readiness of app configuration (secrets via env only). */
    public static function configChecklist(): array
    {
        return [
            ['label' => 'Database (SQLite, WAL)', 'ok' => is_file(Db::dbPath())],
            ['label' => 'SMTP host (email sending)', 'ok' => (string)config('smtp.host') !== ''],
            ['label' => 'Telegram bot token', 'ok' => (string)config('telegram.bot_token') !== ''],
            ['label' => 'Telegram bot username', 'ok' => (string)config('telegram.bot_username') !== ''],
            ['label' => 'Webhook secret set', 'ok' => (string)config('telegram.webhook_secret') !== '' && getenv('TH_TG_WEBHOOK_SECRET') !== false],
            ['label' => 'Base URL (links in emails)', 'ok' => !str_contains((string)config('app.base_url'), 'localhost')],
            ['label' => 'TED API key', 'ok' => (string)config('ted.api_key') !== ''],
            ['label' => 'ReliefWeb appname', 'ok' => (string)getenv('TH_RW_APPNAME') !== '' && getenv('TH_RW_APPNAME') !== false],
        ];
    }
}
