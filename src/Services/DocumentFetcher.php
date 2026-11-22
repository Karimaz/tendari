<?php

namespace Tendari\Services;

use Tendari\Core\Db;
use Tendari\Models\TenderFile;

/**
 * Discovers, mirrors and serves tender documents.
 *
 * - queue():     records document URLs provided by a connector
 * - extractFromPage(): scrapes a tender detail page for document links
 * - snapshotPage(): stores a cached copy of the source page (blank/dead-link protection)
 * - download():  fetches a queued file into storage/tenders/{tenderId}/
 *
 * All fetches are bounded (size/time/redirects) and reject private/loopback hosts.
 */
final class DocumentFetcher
{
    private const MAX_PAGE_BYTES = 3_000_000;
    private const MAX_FILE_BYTES = 15_000_000;
    private const MAX_REDIRECTS = 3;
    private const TIMEOUT = 30;
    private const DOC_EXT = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z', 'csv', 'txt', 'odt', 'ods'];
    private const MIME_EXT = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/x-rar-compressed' => 'rar',
        'application/x-7z-compressed' => '7z',
        'text/csv' => 'csv',
        'text/plain' => 'txt',
        'application/octet-stream' => 'bin',
    ];

    /** Queue document URLs supplied by a connector (expects [['url'=>..., 'label'=>...], ...]). */
    public static function queue(int $tenderId, array $documents): int
    {
        $added = 0;
        foreach (array_slice($documents, 0, 20) as $doc) {
            $url = trim((string)($doc['url'] ?? ''));
            if (!self::safeUrl($url)) {
                continue;
            }
            $label = trim((string)($doc['label'] ?? $doc['description'] ?? ''));
            if (TenderFile::add($tenderId, $url, $label !== '' ? $label : null)) {
                $added++;
            }
        }
        return $added;
    }

    /** Scrape a tender page for links to documents. Returns number queued. */
    public static function extractFromPage(int $tenderId, string $pageUrl): int
    {
        if (!self::safeUrl($pageUrl)) {
            return 0;
        }
        $html = self::fetchText($pageUrl);
        if ($html === null) {
            return 0;
        }
        $base = self::baseUrl($pageUrl);
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $added = 0;
        foreach ($dom->getElementsByTagName('a') as $a) {
            if ($added >= 15) {
                break;
            }
            $href = trim($a->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || preg_match('#^(javascript|mailto|tel):#i', $href)) {
                continue;
            }
            $abs = self::resolveUrl($href, $base);
            if (!self::isDocumentUrl($abs)) {
                continue;
            }
            $label = trim(preg_replace('/\s+/u', ' ', $a->textContent) ?? '');
            if (TenderFile::add($tenderId, $abs, $label !== '' ? $label : null)) {
                $added++;
            }
        }
        return $added;
    }

    /** Store a cached HTML copy of the source page. Returns true when saved. */
    public static function snapshotPage(int $tenderId, string $pageUrl): bool
    {
        if (!self::safeUrl($pageUrl)) {
            return false;
        }
        $existing = Db::one(
            "SELECT id FROM tender_files WHERE tender_id = ? AND kind = 'page' AND status = 'ready'",
            [$tenderId]
        );
        if ($existing !== null) {
            return false;
        }
        $html = self::fetchText($pageUrl);
        if ($html === null || strlen(trim($html)) < 500) {
            return false;
        }
        $sha = hash('sha256', $html);
        $name = 'page-' . substr($sha, 0, 16) . '.html';
        $path = self::storePath($tenderId, $name);
        if (@file_put_contents($path, $html) === false) {
            return false;
        }
        TenderFile::add($tenderId, $pageUrl, 'Cached copy of source page', 'page');
        $row = Db::one('SELECT id FROM tender_files WHERE tender_id = ? AND url = ?', [$tenderId, $pageUrl]);
        if ($row !== null) {
            TenderFile::markReady((int)$row['id'], [
                'mime' => 'text/html',
                'filename' => $name,
                'size' => strlen($html),
                'sha256' => $sha,
                'storage_path' => 'tenders/' . $tenderId . '/' . $name,
            ]);
        }
        return true;
    }

    /** Download one queued file. Returns true on success. */
    public static function download(int $fileId): bool
    {
        $file = TenderFile::find($fileId);
        if ($file === null || $file['status'] === 'ready') {
            return false;
        }
        $url = (string)$file['url'];
        if (!self::safeUrl($url)) {
            TenderFile::markFailed($fileId, 'Unsafe or invalid URL');
            return false;
        }
        $result = self::fetchBinary($url);
        if ($result === null) {
            TenderFile::markFailed($fileId, 'Download failed or unsupported content');
            return false;
        }
        [$body, $mime, $effectiveUrl] = $result;
        if (!self::safeUrl($effectiveUrl)) {
            TenderFile::markFailed($fileId, 'Redirected to an unsafe host');
            return false;
        }
        $sha = hash('sha256', $body);
        // Same document already stored for this tender under another URL? Drop the duplicate row.
        $dup = Db::one(
            "SELECT id FROM tender_files WHERE tender_id = ? AND sha256 = ? AND status = 'ready' AND id <> ?",
            [(int)$file['tender_id'], $sha, $fileId]
        );
        if ($dup !== null) {
            Db::q('DELETE FROM tender_files WHERE id = ?', [$fileId]);
            return true;
        }
        $ext = self::extFor($mime, $url);
        $name = substr($sha, 0, 16) . '.' . $ext;
        $path = self::storePath((int)$file['tender_id'], $name);
        if (@file_put_contents($path, $body) === false) {
            TenderFile::markFailed($fileId, 'Could not write file to storage');
            return false;
        }
        TenderFile::markReady($fileId, [
            'mime' => $mime,
            'filename' => $name,
            'size' => strlen($body),
            'sha256' => $sha,
            'storage_path' => 'tenders/' . (int)$file['tender_id'] . '/' . $name,
        ]);
        return true;
    }

    /** Download queued files for a tender (bounded). Returns number stored. */
    public static function downloadPendingFor(int $tenderId, int $limit = 5): int
    {
        $done = 0;
        foreach (TenderFile::pending($tenderId, $limit) as $file) {
            if (self::download((int)$file['id'])) {
                $done++;
            }
        }
        return $done;
    }

    public static function globalPending(int $limit = 50): array
    {
        return TenderFile::globalPending($limit);
    }

    public static function storePath(int $tenderId, string $name): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/tenders/' . $tenderId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . basename($name);
    }

    public static function absolutePath(string $relative): ?string
    {
        $root = realpath(dirname(__DIR__, 2) . '/storage/tenders');
        if ($root === false) {
            return null;
        }
        $candidate = realpath(dirname(__DIR__, 2) . '/storage/' . ltrim($relative, '/'));
        if ($candidate === false || !str_starts_with($candidate, $root . '/')) {
            return null;
        }
        return is_file($candidate) ? $candidate : null;
    }

    /* ------------------------------------------------------------------ */

    private static function fetchText(string $url): ?string
    {
        $ch = curl_init($url);
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'TendariBot/1.0 (+https://tendari.org; tender documents mirror)',
            CURLOPT_ENCODING => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body) {
                $body .= $chunk;
                return strlen($body) > self::MAX_PAGE_BYTES ? 0 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if ($status >= 400 || $body === '') {
            return null;
        }
        if ($type !== '' && !preg_match('#text/html|application/xhtml|text/plain#i', $type)) {
            return null;
        }
        return $body;
    }

    /** @return array{0:string,1:string,2:string}|null [body, mime, effectiveUrl] */
    private static function fetchBinary(string $url): ?array
    {
        $ch = curl_init($url);
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'TendariBot/1.0 (+https://tendari.org; tender documents mirror)',
            CURLOPT_ENCODING => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body) {
                $body .= $chunk;
                return strlen($body) > self::MAX_FILE_BYTES ? 0 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = strtolower(trim(explode(';', (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE))[0]));
        $effective = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        if ($status >= 400 || $body === '') {
            return null;
        }
        if (str_starts_with($type, 'text/html')) {
            return null; // an error/login page, not a document
        }
        if ($type !== '' && !isset(self::MIME_EXT[$type]) && !str_starts_with($type, 'text/')) {
            // allow unknown binaries when the URL looks like a document
            if (!self::isDocumentUrl($url)) {
                return null;
            }
            $type = 'application/octet-stream';
        }
        return [$body, $type !== '' ? $type : 'application/octet-stream', $effective !== '' ? $effective : $url];
    }

    private static function isDocumentUrl(string $url): bool
    {
        $path = (string)parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, self::DOC_EXT, true)) {
            return true;
        }
        // generic download endpoints, e.g. /download?file=... or /documents/123
        return (bool)preg_match('#/(download|downloads|document|documents|attachment|attachments|file|files)(/|$|\?)#i', $path)
            || (bool)preg_match('#(download|attachment)=#i', (string)parse_url($url, PHP_URL_QUERY));
    }

    private static function extFor(string $mime, string $url): string
    {
        if (isset(self::MIME_EXT[$mime]) && self::MIME_EXT[$mime] !== 'bin') {
            return self::MIME_EXT[$mime];
        }
        $ext = strtolower(pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($ext, self::DOC_EXT, true) ? $ext : (self::MIME_EXT[$mime] ?? 'bin');
    }

    /** Reject non-HTTP(S) URLs and private/loopback/reserved hosts (basic SSRF guard). */
    public static function safeUrl(string $url): bool
    {
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $v4 = gethostbyname($host);
            if ($v4 !== $host) {
                $ips[] = $v4;
            }
            $v6 = @dns_get_record($host, DNS_AAAA) ?: [];
            foreach ($v6 as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }
        foreach ($ips as $ip) {
            $isPublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($isPublic === false) {
                return false;
            }
        }
        return true;
    }

    private static function baseUrl(string $url): string
    {
        $p = parse_url($url);
        if (!isset($p['scheme'], $p['host'])) {
            return $url;
        }
        $base = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $path = $p['path'] ?? '/';
        $dir = rtrim(substr($path, 0, (int)strrpos($path, '/') + 1), '/');
        return $base . ($dir !== '' ? $dir : '');
    }

    private static function resolveUrl(string $href, string $base): string
    {
        $href = trim($href);
        if (str_starts_with($href, '//')) {
            return (parse_url($base, PHP_URL_SCHEME) ?: 'https') . ':' . $href;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $p = parse_url($base);
        $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        return rtrim($base, '/') . '/' . ltrim($href, '/');
    }
}
