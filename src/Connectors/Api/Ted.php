<?php
declare(strict_types=1);

namespace Tendari\Connectors\Api;

use Tendari\Connectors\AbstractConnector;
use Tendari\Connectors\HttpClient;
use Tendari\Services\Normalizer;

/**
 * TED (EU Tenders Electronic Daily) API v3 — only notices whose place of
 * performance is in Africa / the Middle East. Requires TED_API_KEY env.
 */
final class Ted extends AbstractConnector
{
    private const COUNTRIES = [
        'DZ','BH','BI','BJ','BF','CD','CF','CG','CI','CM','CV','DJ','DZ','EG','ER','ET','GA','GH','GM','GN','GQ',
        'GW','JO','KE','KM','KW','LB','LR','LS','LY','MA','MG','ML','MR','MU','MW','MZ','NA','NE','NG','OM','PS',
        'QA','RW','SA','SC','SL','SN','SO','SS','SZ','TD','TG','TN','TZ','UG','YE','ZA','ZM','ZW',
    ];

    public function code(): string
    {
        return 'ted';
    }

    public function probeUrl(): ?string
    {
        return 'https://api.ted.europa.eu/v3/notices/search';
    }

    public function fetch(): array
    {
        $apiKey = (string)config('ted.api_key', '');
        if ($apiKey === '') {
            logger('notice', 'TED_API_KEY not set; skipping TED fetch');
            return [];
        }
        $query = '(performance-country IN (' . implode(',', self::COUNTRIES) . ')) AND (notice-type IN ([1, 2, 3, 4, 7]))';
        $url = 'https://api.ted.europa.eu/v3/notices/search';
        $body = HttpClient::jsonPost($url, [
            'apiKey' => $apiKey,
            'query' => $query,
            'limit' => 100,
            'fields' => ['id', 'notice-title', 'publication-date', 'performance-country', 'buyer-name',
                         'short-description', 'closing-date', 'value-amount', 'value-currency', 'links'],
        ], ['cacheKey' => $this->code()]);
        if ($body === null) {
            return [];
        }
        $json = json_decode($body, true);
        return (array)($json['notices'] ?? $json['results'] ?? []);
    }

    public function normalize(array $raw): ?array
    {
        $title = trim((string)($raw['notice-title'] ?? ''));
        $id = (string)($raw['id'] ?? '');
        $url = (string)($raw['links'][0]['href'] ?? '');
        if ($url === '' && $id !== '') {
            $url = 'https://ted.europa.eu/en/notice/-/detail/' . rawurlencode($id);
        }
        if ($title === '' || $url === '') {
            return null;
        }
        $countries = [];
        foreach ((array)($raw['performance-country'] ?? []) as $c) {
            $c = strtoupper(trim((string)$c));
            if (preg_match('/^[A-Z]{2}$/', $c)) {
                $countries[] = $c;
            }
        }
        $amount = isset($raw['value-amount']) && is_numeric($raw['value-amount']) ? (float)$raw['value-amount'] : null;
        $published = Normalizer::date($raw['publication-date'] ?? null);
        $deadline = Normalizer::date($raw['closing-date'] ?? null);

        return [
            'source_ref' => $id !== '' ? $id : null,
            'title' => $title,
            'description' => (string)($raw['short-description'] ?? ''),
            'organization' => (string)($raw['buyer-name'] ?? ''),
            'url' => $url,
            'countries' => $countries,
            'sectors' => [],
            'funding_type' => 'procurement',
            'value_amount' => $amount,
            'value_currency' => isset($raw['value-currency']) ? strtoupper((string)$raw['value-currency']) : null,
            'published_at' => $published,
            'deadline_at' => $deadline,
            'status' => Normalizer::status($published ?? gmdate('Y-m-d H:i:s'), $deadline),
        ];
    }
}
