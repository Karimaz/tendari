<?php
declare(strict_types=1);

namespace Tendari\Connectors;

/**
 * Base connector: polite HTTP client (1 req / 2s per host, robots-friendly UA),
 * stores raw payloads in storage/cache/ for debugging parsers.
 */
abstract class AbstractConnector implements ConnectorInterface
{
    protected array $sourceConfig = [];
    protected string $codeName = '';

    public function __construct(array $sourceConfig = [], string $code = '')
    {
        $this->sourceConfig = $sourceConfig;
        $this->codeName = $code;
    }

    public function code(): string
    {
        return $this->codeName;
    }

    /** Cheap URL used by the health checker to verify the endpoint is alive. */
    public function probeUrl(): ?string
    {
        return null;
    }

    /** True when the probe URL is expected to return a JSON document. */
    public function expectJson(): bool
    {
        return false;
    }

    protected function httpGet(string $url, array $headers = []): ?string
    {
        return HttpClient::get($url, $headers, $this->cacheKey());
    }

    protected function cacheKey(): string
    {
        return $this->code();
    }

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return $this->sourceConfig[$key] ?? $default;
    }
}

final class RegistrationRequiredException extends \RuntimeException
{
}

/** Shared HTTP layer with per-host politeness delay + raw payload caching. */
final class HttpClient
{
    private static array $lastHit = [];

    public static function get(string $url, array $headers = [], string $cacheDirKey = 'default'): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'unknown';
        self::politenessDelay($host);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'TendariBot/1.0 (+https://tendari.org; respectful aggregator)',
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);

        if ($body === false || $status >= 400) {
            logger('warning', 'HTTP fetch failed', ['url' => $url, 'status' => $status, 'error' => $err]);
            return null;
        }

        self::cacheRaw($cacheDirKey, $url, (string)$body);
        return (string)$body;
    }

    /** POST JSON and return the response body (still cached for debugging). */
    public static function jsonPost(string $url, array $payload, array $opts = []): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'unknown';
        self::politenessDelay($host);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'TendariBot/1.0 (+https://tendari.org; respectful aggregator)',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false || $status >= 400) {
            logger('warning', 'HTTP JSON POST failed', ['url' => $url, 'status' => $status]);
            return null;
        }
        self::cacheRaw($opts['cacheKey'] ?? 'default', $url, (string)$body);
        return (string)$body;
    }

    /** One-shot probe: HTTP status + latency + head of body (no caching). */
    public static function probe(string $url, int $timeout = 12): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => 'TendariBot/1.0 (health-check)',
            CURLOPT_ENCODING => '',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $latency = (int)round(1000 * (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME));
        $err = curl_error($ch);

        if ($body === false) {
            logger('warning', 'Health probe failed', ['url' => $url, 'error' => $err]);
            return null;
        }
        return ['code' => $code, 'latency_ms' => $latency, 'body' => substr((string)$body, 0, 4000)];
    }

    private static function politenessDelay(string $host): void
    {
        $now = microtime(true);
        $last = self::$lastHit[$host] ?? 0;
        $wait = 2.0 - ($now - $last);
        if ($wait > 0) {
            usleep((int)($wait * 1_000_000));
        }
        self::$lastHit[$host] = microtime(true);
    }

    private static function cacheRaw(string $key, string $url, string $body): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache/' . preg_replace('/[^a-z0-9_-]/i', '', $key);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . gmdate('Ymd-His') . '-' . substr(md5($url), 0, 10);
        @file_put_contents($file, $body);
    }
}
