<?php
declare(strict_types=1);

namespace Tendari\Connectors\Api;

use Tendari\Connectors\AbstractConnector;
use Tendari\Services\Normalizer;

/**
 * OCHA FTS (HPC.tools) — humanitarian funding flows / grants.
 * Endpoint: https://api.hpc.tools/v1/public/fts/flow?countryISO3=...&year=...
 */
final class OchaFts extends AbstractConnector
{
    private const ISO3 = [
        'DZA','BHR','BEN','BFA','BDI','CMR','CAF','TCD','COM','COD','COG','CIV','DJI','EGY','ERI','SWZ',
        'ETH','GAB','GMB','GHA','GIN','GNB','GNQ','IRQ','JOR','KEN','KWT','LBN','LBR','LBY','MAR','MDG',
        'MLI','MRT','MUS','MOZ','MWI','NAM','NER','NGA','OMN','PSE','QAT','RWA','SAU','SDN','SEN','SLE',
        'SOM','SSD','SYR','TGO','TUN','TZA','UGA','YEM','ZAF','ZMB','ZWE',
    ];

    public function code(): string
    {
        return 'fts';
    }

    public function probeUrl(): ?string
    {
        return 'https://api.hpc.tools/v1/public/fts/flow?countryISO3=KEN&year=' . (int)gmdate('Y');
    }

    public function expectJson(): bool
    {
        return true;
    }

    public function fetch(): array
    {
        $year = (int)gmdate('Y');
        $url = 'https://api.hpc.tools/v1/public/fts/flow?countryISO3=' . implode(',', self::ISO3)
             . '&year=' . $year;
        $body = $this->httpGet($url);
        if ($body === null) {
            return [];
        }
        $json = json_decode($body, true);
        $flows = (array)($json['data']['flows'] ?? []);
        // only flows reported recently (last 30 days)
        $cutoff = time() - 30 * 86400;
        return array_values(array_filter($flows, function ($f) use ($cutoff) {
            $ts = strtotime((string)($f['date'] ?? $f['createdAt'] ?? ''));
            return $ts !== false && $ts >= $cutoff;
        }));
    }

    public function normalize(array $raw): ?array
    {
        $description = trim((string)($raw['description'] ?? ''));
        $source = (string)($raw['sourceObject'][0]['name'] ?? $raw['sourceObject']['name'] ?? '');
        $ref = (string)($raw['id'] ?? '');
        if ($description === '' && $source === '') {
            return null;
        }
        $title = $description !== '' ? $description : ($source . ' contribution');
        $url = 'https://gms.unocha.org/content/flow/' . ($ref !== '' ? $ref : 'overview');

        $countries = [];
        $locs = $raw['destinationObject']['location'] ?? $raw['destinationLocation'] ?? [];
        foreach ((array)$locs as $loc) {
            $countries = array_merge($countries, Normalizer::countries((string)($loc['name'] ?? '')));
        }

        $published = Normalizer::date((string)($raw['date'] ?? $raw['decisionDate'] ?? ''));
        $amount = isset($raw['amountUSD']) && is_numeric($raw['amountUSD']) && (float)$raw['amountUSD'] > 0
            ? (float)$raw['amountUSD'] : null;

        return [
            'source_ref' => $ref !== '' ? $ref : null,
            'title' => $title,
            'description' => 'Humanitarian funding flow via OCHA FTS',
            'organization' => $source,
            'url' => $url,
            'countries' => array_values(array_unique($countries)),
            'sectors' => [],
            'funding_type' => 'grant',
            'value_amount' => $amount,
            'value_currency' => $amount !== null ? 'USD' : null,
            'published_at' => $published,
            'deadline_at' => null,
            'status' => 'open',
        ];
    }
}
