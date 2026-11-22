<?php
declare(strict_types=1);

namespace Tendari\Connectors\Web;

use Tendari\Services\Normalizer;

/** African Development Bank — procurement notices (grouped list pages). */
final class Afdb extends HtmlConnector
{
    public function code(): string
    {
        return 'afdb';
    }

    public function probeUrl(): ?string
    {
        return 'https://www.afdb.org/en/topics-opportunities/procurement/public-procurement-notices';
    }

    public function fetch(): array
    {
        $rows = [];
        $listUrl = 'https://www.afdb.org/en/topics-opportunities/procurement/public-procurement-notices';
        $html = $this->httpGet($listUrl);
        if ($html === null) {
            return [];
        }
        $links = $this->xpath($html, '//a[contains(@href,"/procurement-notices/")]');
        if ($links === null) {
            return [];
        }
        $seen = [];
        foreach ($links as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || isset($seen[$href])) {
                continue;
            }
            $seen[$href] = true;
            $detailUrl = str_starts_with($href, 'http') ? $href : 'https://www.afdb.org' . $href;
            $detail = $this->httpGet($detailUrl);
            if ($detail === null) {
                continue;
            }
            $rows[] = ['_url' => $detailUrl, '_html' => $detail, '_list_title' => trim($a->textContent)];
        }
        return $rows;
    }

    public function normalize(array $raw): ?array
    {
        $html = (string)($raw['_html'] ?? '');
        $url = (string)($raw['_url'] ?? '');
        $title = trim((string)($raw['_list_title'] ?? ''));
        if ($html === '' || $url === '' || $title === '') {
            return null;
        }
        $descList = $this->xpath($html, '//div[contains(@class,"content-body")]//p[1]');
        if ($descList === null || $descList->length === 0) {
            $descList = $this->xpath($html, '//p[1]');
        }
        $description = $descList !== null ? $this->first($descList) : '';
        $orgList = $this->xpath($html, '//table//tr[contains(th,"Borrower")]/td');
        $org = $orgList !== null ? $this->first($orgList) : '';
        $deadlineList = $this->xpath($html, '//table//tr[contains(th,"Deadline")]/td');
        $deadline = $deadlineList !== null ? $this->first($deadlineList) : '';

        return [
            'source_ref' => null,
            'title' => $title,
            'description' => Normalizer::cleanText($description, 4000),
            'organization' => $org !== '' ? $org : 'African Development Bank',
            'url' => $url,
            'countries' => [],
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
