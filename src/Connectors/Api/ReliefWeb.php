<?php
declare(strict_types=1);

namespace Tendari\Connectors\Api;

use Tendari\Connectors\AbstractConnector;
use Tendari\Services\Normalizer;

/**
 * ReliefWeb API v2 — jobs & training (consultancies often announce tenders here).
 * Requires an approved appname: set TH_RW_APPNAME (request at apidoc.reliefweb.int).
 */
final class ReliefWeb extends AbstractConnector
{
    public function code(): string
    {
        return 'reliefweb';
    }

    public function probeUrl(): ?string
    {
        $appname = (string)getenv('TH_RW_APPNAME') ?: 'tenderhunter';
        return 'https://api.reliefweb.int/v2/jobs?appname=' . rawurlencode($appname) . '&limit=1&fields[include][]=title';
    }

    public function expectJson(): bool
    {
        return true;
    }

    public function fetch(): array
    {
        $appname = (string)getenv('TH_RW_APPNAME') ?: 'tenderhunter';
        $rows = [];
        foreach (['jobs', 'training'] as $resource) {
            $url = 'https://api.reliefweb.int/v2/' . $resource
                 . '?appname=' . rawurlencode($appname) . '&limit=50&sort[]=date:desc'
                 . '&fields[include][]=title&fields[include][]=url&fields[include][]=id'
                 . '&fields[include][]=date&fields[include][]=country&fields[include][]=source'
                 . '&fields[include][]=description_text';
            $body = $this->httpGet($url);
            if ($body === null) {
                continue;
            }
            $json = json_decode($body, true);
            if (isset($json['status']) && $json['status'] >= 400) {
                logger('warning', 'ReliefWeb API rejected appname: ' . ($json['error']['message'] ?? '?'));
                continue;
            }
            $data = $json['data'] ?? [];
            foreach ((array)$data as $item) {
                if (isset($item['fields'])) {
                    $item['fields']['_resource'] = $resource;
                    $rows[] = $item['fields'];
                }
            }
        }
        return $rows;
    }

    public function normalize(array $raw): ?array
    {
        $title = trim((string)($raw['title'] ?? ''));
        $url = (string)($raw['url'] ?? '');
        if ($title === '' || $url === '') {
            return null;
        }
        $countries = [];
        foreach ((array)($raw['country'] ?? []) as $c) {
            $name = (string)($c['name'] ?? '');
            $iso = strtoupper((string)($c['iso3'] ?? ''));
            $fromIso3 = [
                'KEN' => 'KE', 'UGA' => 'UG', 'RWA' => 'RW', 'TZA' => 'TZ', 'NGA' => 'NG',
                'GHA' => 'GH', 'ETH' => 'ET', 'SOM' => 'SO', 'ZAF' => 'ZA', 'SDN' => 'SD',
            ];
            if (isset($fromIso3[$iso])) {
                $countries[] = $fromIso3[$iso];
            } else {
                $countries = array_merge($countries, Normalizer::countries($name));
            }
        }

        $published = Normalizer::date($raw['date']['published'] ?? $raw['date']['created'] ?? null);
        $deadline = Normalizer::date($raw['date']['closing'] ?? null);
        $resource = (string)($raw['_resource'] ?? 'jobs');

        return [
            'source_ref' => (string)($raw['id'] ?? ''),
            'title' => $title,
            'description' => Normalizer::cleanText((string)($raw['description_text'] ?? ''), 4000),
            'organization' => (string)($raw['source'][0]['shortname'] ?? ''),
            'url' => $url,
            'countries' => array_values(array_unique($countries)),
            'sectors' => [],
            'funding_type' => $resource === 'jobs' ? 'framework' : 'unknown',
            'value_amount' => null,
            'value_currency' => null,
            'published_at' => $published,
            'deadline_at' => $deadline,
            'status' => Normalizer::status($published ?? gmdate('Y-m-d H:i:s'), $deadline),
        ];
    }
}
