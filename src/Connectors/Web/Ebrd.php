<?php
declare(strict_types=1);

namespace Tendari\Connectors\Web;

use Tendari\Services\Normalizer;

/** EBRD procurement notices list. */
final class Ebrd extends HtmlConnector
{
    public function code(): string
    {
        return 'ebrd';
    }

    public function probeUrl(): ?string
    {
        return 'https://www.ebrd.com/home/work-with-us/project-procurement.html';
    }

    public function fetch(): array
    {
        $url = 'https://www.ebrd.com/home/work-with-us/project-procurement.html';
        $html = $this->httpGet($url);
        if ($html === null) {
            return [];
        }
        $nodes = $this->xpath($html, '//table//tr[td]');
        $rows = [];
        if ($nodes !== null) {
            foreach ($nodes as $tr) {
                $cells = [];
                foreach ($tr->getElementsByTagName('td') as $td) {
                    $cells[] = trim($td->textContent);
                }
                $linkNode = $tr->getElementsByTagName('a')->item(0);
                $rows[] = [
                    '_cells' => $cells,
                    '_href' => $linkNode ? trim($linkNode->getAttribute('href')) : '',
                ];
            }
        }
        return $rows;
    }

    public function normalize(array $raw): ?array
    {
        $cells = (array)($raw['_cells'] ?? []);
        $href = (string)($raw['_href'] ?? '');
        $title = $cells[0] ?? '';
        if ($title === '' || $href === '') {
            return null;
        }
        $url = str_starts_with($href, 'http') ? $href : 'https://www.ebrd.com/home' . $href;
        // cells: title, type, country, closing date...
        $countryName = $cells[2] ?? '';
        $deadline = $cells[3] ?? ($cells[count($cells) - 1] ?? '');

        return [
            'source_ref' => null,
            'title' => $title,
            'description' => '',
            'organization' => 'EBRD',
            'url' => $url,
            'countries' => Normalizer::countries($countryName),
            'sectors' => [],
            'funding_type' => 'procurement',
            'value_amount' => null,
            'value_currency' => null,
            'published_at' => null,
            'deadline_at' => Normalizer::date($deadline),
            'status' => 'open',
        ];
    }
}
