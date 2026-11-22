<?php
declare(strict_types=1);

namespace Tendari\Connectors\Web;

use Tendari\Services\Normalizer;

/** South Africa eTenders portal (etenders.gov.za) — published tender list pages. */
final class ZaEtenders extends HtmlConnector
{
    public function code(): string
    {
        return 'za_etenders';
    }

    public function probeUrl(): ?string
    {
        return 'https://www.etenders.gov.za/Home/opportunities?id=1';
    }

    public function fetch(): array
    {
        $url = 'https://www.etenders.gov.za/Home/opportunities?id=1';
        $html = $this->httpGet($url);
        if ($html === null) {
            return [];
        }
        $rows = $this->xpath($html, '//table[contains(@class,"views-table")]//tr[td]');
        $out = [];
        if ($rows !== null) {
            foreach ($rows as $tr) {
                $cells = [];
                foreach ($tr->getElementsByTagName('td') as $td) {
                    $cells[] = trim($td->textContent);
                }
                $linkNode = $tr->getElementsByTagName('a')->item(0);
                $out[] = [
                    '_cells' => $cells,
                    '_href' => $linkNode ? trim($linkNode->getAttribute('href')) : '',
                ];
            }
        }
        return $out;
    }

    public function normalize(array $raw): ?array
    {
        $cells = (array)($raw['_cells'] ?? []);
        $href = (string)($raw['_href'] ?? '');
        $title = $cells[0] ?? '';
        if ($title === '' || $href === '') {
            return null;
        }
        $url = str_starts_with($href, 'http') ? $href : 'https://www.etenders.gov.za' . $href;
        $deadline = $cells[3] ?? '';

        return [
            'source_ref' => null,
            'title' => $title,
            'description' => '',
            'organization' => $cells[1] ?? 'South African government',
            'url' => $url,
            'countries' => ['ZA'],
            'sectors' => [],
            'funding_type' => 'procurement',
            'value_amount' => null,
            'value_currency' => 'ZAR',
            'published_at' => null,
            'deadline_at' => Normalizer::date($deadline),
            'status' => 'open',
        ];
    }
}
