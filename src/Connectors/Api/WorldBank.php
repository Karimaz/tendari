<?php
declare(strict_types=1);

namespace Tendari\Connectors\Api;

use Tendari\Connectors\AbstractConnector;
use Tendari\Connectors\AwardsConnectorInterface;
use Tendari\Services\Normalizer;

/**
 * World Bank procurement notices — public JSON API (no auth).
 * Endpoint: https://search.worldbank.org/api/v2/procnotices
 */
final class WorldBank extends AbstractConnector implements AwardsConnectorInterface
{
    private const NOTICE_TYPES = [
        'Invitation for Bids',
        'Request for Expression of Interest',
        'Request for Proposal',
        'Contract Award',
    ];

    public function code(): string
    {
        return 'worldbank';
    }

    public function probeUrl(): ?string
    {
        return 'https://search.worldbank.org/api/v2/procnotices?format=json&rows=1';
    }

    public function expectJson(): bool
    {
        return true;
    }

    public function fetch(): array
    {
        $rows = [];
        $pageSize = 100;
        foreach (self::NOTICE_TYPES as $type) {
            $offset = 0;
            for ($page = 0; $page < 3; $page++) {
                $url = 'https://search.worldbank.org/api/v2/procnotices?format=json'
                     . '&rows=' . $pageSize . '&os=' . $offset
                     . '&sortDesc=noticedate'
                     . '&notice_status=Published'
                     . '&notice_type=' . rawurlencode($type);
                $body = $this->httpGet($url);
                if ($body === null) {
                    break;
                }
                $json = json_decode($body, true);
                $docs = (array)($json['procnotices'] ?? []);
                if ($docs === []) {
                    break;
                }
                $rows = array_merge($rows, $docs);
                $offset += $pageSize;
                if ($offset >= (int)($json['total'] ?? 0)) {
                    break;
                }
            }
        }
        return $rows;
    }

    public function normalize(array $raw): ?array
    {
        $title = trim((string)($raw['bid_description'] ?? $raw['project_name'] ?? ''));
        $id = (string)($raw['id'] ?? '');
        if ($title === '' || $id === '') {
            return null;
        }
        // Notice detail page (project_id points at the project, not the notice).
        $url = 'https://projects.worldbank.org/en/projects-operations/procurement-detail/' . rawurlencode($id);

        $countries = Normalizer::countries((string)($raw['project_ctry_name'] ?? ''));
        $deadline = Normalizer::date((string)($raw['submission_deadline_date'] ?? ''));
        $published = Normalizer::date((string)($raw['noticedate'] ?? ''));
        $status = 'open';
        if (str_contains(strtolower((string)($raw['notice_type'] ?? '')), 'award')) {
            $status = 'awarded';
        } else {
            $status = Normalizer::status($published ?? gmdate('Y-m-d H:i:s'), $deadline);
        }

        return [
            'source_ref' => $id,
            'title' => $title,
            'description' => Normalizer::cleanText((string)($raw['notice_text'] ?? $raw['bid_description'] ?? ''), 4000),
            'organization' => trim((string)($raw['contact_organization'] ?? $raw['project_name'] ?? 'World Bank')) ?: 'World Bank',
            'url' => $url,
            'countries' => $countries,
            'sectors' => [],
            'funding_type' => Normalizer::fundingType((string)($raw['procurement_method_name'] ?? '')),
            'value_amount' => null,
            'value_currency' => null,
            'published_at' => $published,
            'deadline_at' => $deadline,
            'status' => $status,
        ];
    }

    public function normalizeAward(array $raw): ?array
    {
        if (!str_contains(strtolower((string)($raw['notice_type'] ?? '')), 'award')) {
            return null;
        }
        $title = trim((string)($raw['bid_description'] ?? $raw['project_name'] ?? ''));
        $id = (string)($raw['id'] ?? '');
        if ($title === '' || $id === '') {
            return null;
        }
        $url = 'https://projects.worldbank.org/en/projects-operations/procurement-detail/' . rawurlencode($id);

        $winner = trim((string)($raw['awarded_firm'] ?? $raw['contractor_name'] ?? $raw['supplier_name'] ?? ''));
        [$amount, $currency] = Normalizer::money(
            $raw['award_amount'] ?? $raw['contract_amount'] ?? $raw['total_contract_amount'] ?? ''
        );

        return [
            'source_ref' => $id,
            'source_url' => $url,
            'title' => $title,
            'description' => Normalizer::cleanText((string)($raw['notice_text'] ?? $title), 4000),
            'buyer' => trim((string)($raw['contact_organization'] ?? $raw['project_name'] ?? 'World Bank')) ?: 'World Bank',
            'winner' => $winner,
            'contract_ref' => trim((string)($raw['bid_reference_no'] ?? '')) ?: null,
            'countries' => Normalizer::countries((string)($raw['project_ctry_name'] ?? '')),
            'sectors' => [],
            'amount' => $amount,
            'currency' => $currency,
            'awarded_at' => Normalizer::date(
                $raw['award_date'] ?? $raw['contract_signature_date'] ?? $raw['noticedate'] ?? null
            ),
        ];
    }
}
