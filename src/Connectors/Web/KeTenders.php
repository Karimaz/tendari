<?php
declare(strict_types=1);

namespace Tendari\Connectors\Web;

use Tendari\Services\Normalizer;

/** Kenya Public Procurement Information Portal (tenders.go.ke) — public JSON API. */
final class KeTenders extends HtmlConnector
{
    private const API = 'https://tenders.go.ke/api/active-tenders';

    public function code(): string
    {
        return 'ke_tenders';
    }

    public function probeUrl(): ?string
    {
        return self::API . '?page=1';
    }

    public function expectJson(): bool
    {
        return true;
    }

    public function fetch(): array
    {
        $rows = [];
        for ($page = 1; $page <= 4; $page++) {
            $body = $this->httpGet(self::API . '?page=' . $page);
            if ($body === null) {
                break;
            }
            $json = json_decode($body, true);
            $items = (array)($json['data'] ?? []);
            if ($items === []) {
                break;
            }
            $rows = array_merge($rows, $items);
            $last = (int)($json['last_page'] ?? $page);
            if ($page >= $last) {
                break;
            }
        }
        return $rows;
    }

    public function normalize(array $raw): ?array
    {
        $title = trim((string)($raw['title'] ?? ''));
        $id = (string)($raw['id'] ?? '');
        if ($title === '' || $id === '') {
            return null;
        }
        $deadline = Normalizer::date($raw['close_at'] ?? null);
        $published = Normalizer::date($raw['published_at'] ?? null);
        $org = trim((string)($raw['pe']['name'] ?? ''));

        $documents = [];
        foreach ((array)($raw['documents'] ?? []) as $doc) {
            $u = trim((string)($doc['url'] ?? ''));
            if ($u === '') {
                continue;
            }
            if (str_starts_with($u, '/')) {
                $u = 'https://tenders.go.ke' . $u;
            }
            $documents[] = [
                'url' => $u,
                'label' => (string)($doc['description'] ?? $doc['document_type']['name'] ?? 'Tender document'),
            ];
        }

        return [
            'source_ref' => $id,
            'title' => $title,
            'description' => trim((string)($raw['description'] ?? '')),
            'organization' => $org !== '' ? $org : 'Government of Kenya',
            'url' => 'https://tenders.go.ke/tenders/' . rawurlencode($id),
            'countries' => ['KE'],
            'sectors' => [],
            'funding_type' => 'procurement',
            'value_amount' => null,
            'value_currency' => 'KES',
            'published_at' => $published,
            'deadline_at' => $deadline,
            'status' => Normalizer::status($published ?? gmdate('Y-m-d H:i:s'), $deadline),
            'documents' => $documents,
        ];
    }
}
