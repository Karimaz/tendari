<?php
declare(strict_types=1);

namespace Tendari\Services;

use Tendari\Core\Db;

/**
 * Award intelligence: ingest award notices, find comparable past awards and
 * derive price ranges once enough comparable awards exist in a sector.
 *
 * Deliberately data-first: no model is trained. "Similar" uses shared sectors,
 * countries and title tokens; price bands are simple per-currency quantiles.
 */
final class AwardService
{
    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'from', 'this', 'that', 'supply', 'supplies', 'service',
        'services', 'procurement', 'contract', 'contracts', 'project', 'projects', 'works',
        'goods', 'bid', 'bids', 'tender', 'tenders', 'request', 'requests', 'invitation',
        'expression', 'interest', 'proposal', 'proposals', 'quotation', 'consultancy',
        'consulting', 'delivery', 'provision', 'purchase', 'technical', 'support',
    ];

    /** Ingest one award record (connector payload or derived from an awarded notice). */
    public static function record(int $sourceId, array $a): ?int
    {
        $url = trim((string)($a['source_url'] ?? $a['url'] ?? ''));
        $title = Normalizer::title(trim((string)($a['title'] ?? '')));
        if ($url === '' || $title === '') {
            return null;
        }
        $sourceRef = trim((string)($a['source_ref'] ?? ''));
        $key = $sourceRef !== ''
            ? $sourceId . '|ref:' . $sourceRef
            : $sourceId . '|url:' . $url . '|' . mb_substr(Normalizer::textKey($title), 0, 80);
        $canonical = md5($key);

        $countries = json_encode(Normalizer::countries($a['countries'] ?? []));
        $sectors = json_encode(json_list($a['sectors'] ?? []));
        $buyer = trim((string)($a['buyer'] ?? $a['organization'] ?? ''));
        $winner = trim((string)($a['winner'] ?? $a['supplier'] ?? $a['awardee'] ?? ''));
        $description = (string)($a['description'] ?? '');
        $contractRef = trim((string)($a['contract_ref'] ?? '')) ?: null;
        $tenderId = isset($a['tender_id']) && (int)$a['tender_id'] > 0 ? (int)$a['tender_id'] : null;

        [$amount, $currency] = self::money($a);
        $awardedAt = Normalizer::date(
            $a['awarded_at'] ?? $a['award_date'] ?? $a['published_at'] ?? $a['notice_date'] ?? null
        );
        $searchText = Normalizer::textKey(
            $title . ' ' . $description . ' ' . $buyer . ' ' . $winner . ' ' .
            implode(' ', json_list($sectors)) . ' ' . implode(' ', json_list($countries))
        );

        Db::q(
            'INSERT INTO awards (canonical_id, tender_id, source_id, source_ref, source_url, title, description,
                                 buyer, winner, contract_ref, countries, sectors, amount, currency, awarded_at,
                                 raw, search_text)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON CONFLICT(canonical_id) DO UPDATE SET
                tender_id = COALESCE(excluded.tender_id, awards.tender_id),
                title = CASE WHEN length(excluded.title) > length(awards.title) THEN excluded.title ELSE awards.title END,
                description = CASE WHEN length(excluded.description) > length(awards.description) THEN excluded.description ELSE awards.description END,
                buyer = CASE WHEN excluded.buyer <> \'\' THEN excluded.buyer ELSE awards.buyer END,
                winner = CASE WHEN excluded.winner <> \'\' THEN excluded.winner ELSE awards.winner END,
                contract_ref = COALESCE(excluded.contract_ref, awards.contract_ref),
                countries = CASE WHEN length(excluded.countries) > length(awards.countries) THEN excluded.countries ELSE awards.countries END,
                sectors = CASE WHEN length(excluded.sectors) > length(awards.sectors) THEN excluded.sectors ELSE awards.sectors END,
                amount = COALESCE(excluded.amount, awards.amount),
                currency = COALESCE(excluded.currency, awards.currency),
                awarded_at = COALESCE(excluded.awarded_at, awards.awarded_at),
                raw = COALESCE(excluded.raw, awards.raw),
                search_text = COALESCE(NULLIF(excluded.search_text, \'\'), awards.search_text),
                updated_at = datetime(\'now\')',
            [
                $canonical, $tenderId, $sourceId, $sourceRef !== '' ? $sourceRef : null, $url,
                $title, $description, $buyer, $winner, $contractRef, $countries, $sectors,
                $amount, $currency, $awardedAt, json_encode($a, JSON_UNESCAPED_UNICODE), $searchText,
            ]
        );
        $id = Db::scalar('SELECT id FROM awards WHERE canonical_id = ?', [$canonical]);
        return $id !== null ? (int)$id : null;
    }

    /** Derive an award record from a notice the connector already marked as awarded. */
    public static function recordFromTender(int $sourceId, array $norm, int $tenderId): ?int
    {
        if (($norm['status'] ?? '') !== 'awarded') {
            return null;
        }
        return self::record($sourceId, [
            'tender_id' => $tenderId,
            'source_ref' => $norm['source_ref'] ?? null,
            'source_url' => $norm['url'] ?? '',
            'title' => $norm['title'] ?? '',
            'description' => $norm['description'] ?? '',
            'buyer' => $norm['organization'] ?? '',
            'winner' => $norm['winner'] ?? '',
            'contract_ref' => $norm['contract_ref'] ?? null,
            'countries' => $norm['countries'] ?? [],
            'sectors' => $norm['sectors'] ?? [],
            'amount' => $norm['value_amount'] ?? null,
            'currency' => $norm['value_currency'] ?? null,
            'awarded_at' => $norm['awarded_at'] ?? $norm['published_at'] ?? null,
        ]);
    }

    /** Mirror already-ingested awarded notices into the awards table (idempotent). */
    public static function backfillFromAwardedTenders(): array
    {
        $rows = Db::all("SELECT * FROM tenders WHERE status = 'awarded' ORDER BY id");
        $recorded = 0;
        foreach ($rows as $row) {
            $id = self::recordFromTender((int)$row['source_id'], [
                'status' => 'awarded',
                'source_ref' => $row['source_ref'],
                'url' => $row['source_url'],
                'title' => $row['title'],
                'description' => $row['description'],
                'organization' => $row['organization'],
                'countries' => json_list($row['countries']),
                'sectors' => json_list($row['sectors']),
                'value_amount' => $row['value_amount'],
                'value_currency' => $row['value_currency'],
                'published_at' => $row['published_at'],
            ], (int)$row['id']);
            if ($id !== null) {
                $recorded++;
            }
        }
        return ['scanned' => count($rows), 'awards' => $recorded];
    }

    /** Everything the tender page needs: comparable awards + price bands. */
    public static function intelligence(array $tender, ?int $minSample = null): array
    {
        $minSample = $minSample ?? max(1, (int)config('app.award_min_sample', 5));
        $pricing = self::priceBands($tender, $minSample);
        return [
            'similar' => self::similarForTender($tender, 8),
            'bands' => $pricing['bands'],
            'sample' => $pricing['sample'],
            'min_sample' => $pricing['min_sample'],
            'basis' => $pricing['basis'],
        ];
    }

    /** Awards similar to a tender, ranked by sector/country/title overlap. */
    public static function similarForTender(array $tender, int $limit = 8): array
    {
        $sectors = self::lower(json_list($tender['sectors'] ?? []));
        $countries = json_list($tender['countries'] ?? []);
        $tokens = self::tokens((string)($tender['title'] ?? ''));
        $tenderId = (int)($tender['id'] ?? 0);

        $pool = [];
        $conditions = [];
        $params = [];
        foreach ($sectors as $s) {
            $conditions[] = 'a.sectors LIKE ?';
            $params[] = '%' . $s . '%';
        }
        foreach ($countries as $c) {
            $conditions[] = 'a.countries LIKE ?';
            $params[] = '%' . $c . '%';
        }
        if ($conditions !== []) {
            $rows = Db::all(
                'SELECT a.*, s.name AS source_name FROM awards a
                 JOIN sources s ON s.id = a.source_id
                 WHERE (' . implode(' OR ', $conditions) . ')
                 ORDER BY COALESCE(a.awarded_at, a.first_seen_at) DESC LIMIT 400',
                $params
            );
            foreach ($rows as $row) {
                $pool[(int)$row['id']] = $row;
            }
        }

        if ($tokens !== []) {
            $match = implode(' OR ', array_map(
                fn($t) => '"' . str_replace('"', ' ', $t) . '"',
                array_slice($tokens, 0, 8)
            ));
            try {
                $rows = Db::all(
                    'SELECT a.*, s.name AS source_name FROM awards a
                     JOIN awards_fts f ON f.rowid = a.id
                     JOIN sources s ON s.id = a.source_id
                     WHERE awards_fts MATCH ?
                     ORDER BY COALESCE(a.awarded_at, a.first_seen_at) DESC LIMIT 200',
                    [$match]
                );
                foreach ($rows as $row) {
                    $pool[(int)$row['id']] = $row;
                }
            } catch (\Throwable) {
                // malformed FTS query -> keep sector/country candidates
            }
        }

        if ($pool === []) {
            foreach (Db::all(
                'SELECT a.*, s.name AS source_name FROM awards a
                 JOIN sources s ON s.id = a.source_id
                 ORDER BY COALESCE(a.awarded_at, a.first_seen_at) DESC LIMIT 200'
            ) as $row) {
                $pool[(int)$row['id']] = $row;
            }
        }

        $scored = [];
        foreach ($pool as $award) {
            if ($tenderId > 0 && (int)($award['tender_id'] ?? 0) === $tenderId) {
                continue;
            }
            $score = self::score($award, $sectors, $countries, $tokens);
            if ($score <= 0) {
                continue;
            }
            $award['relevance'] = $score;
            $scored[] = $award;
        }
        usort($scored, static function (array $a, array $b): int {
            $cmp = [$b['relevance'], $b['awarded_at'] ?? $b['first_seen_at'] ?? '']
                <=> [$a['relevance'], $a['awarded_at'] ?? $a['first_seen_at'] ?? ''];
            return $cmp !== 0 ? $cmp : ((int)$b['id'] <=> (int)$a['id']);
        });
        return array_slice($scored, 0, max(0, $limit));
    }

    /**
     * Per-currency price bands for a tender's sector (fallback: countries).
     * Returns only bands backed by at least $minSample comparable amounts.
     *
     * @return array{bands:array,sample:int,min_sample:int,basis:string}
     */
    public static function priceBands(array $tender, int $minSample = 5): array
    {
        $minSample = max(1, $minSample);
        $sectors = self::lower(json_list($tender['sectors'] ?? []));
        $countries = json_list($tender['countries'] ?? []);
        $yourValue = isset($tender['value_amount']) && is_numeric($tender['value_amount'])
            ? (float)$tender['value_amount'] : null;
        $yourCurrency = strtoupper((string)($tender['value_currency'] ?? ''));

        $scope = null;
        if ($sectors !== []) {
            $scope = self::amountScope($sectors, [], $minSample, $yourValue, $yourCurrency) + ['basis' => 'sector'];
            if ($scope['bands'] !== []) {
                return $scope;
            }
        }
        if ($countries !== []) {
            $countryScope = self::amountScope([], $countries, $minSample, $yourValue, $yourCurrency) + ['basis' => 'country'];
            if ($countryScope['bands'] !== [] || $scope === null || $countryScope['sample'] > $scope['sample']) {
                $scope = $countryScope;
            }
        }
        return $scope ?? ['bands' => [], 'sample' => 0, 'min_sample' => $minSample, 'basis' => 'none'];
    }

    /** @return array{bands:array,sample:int,min_sample:int} */
    private static function amountScope(
        array $sectors,
        array $countries,
        int $minSample,
        ?float $yourValue,
        string $yourCurrency
    ): array {
        $conditions = [];
        $params = [];
        foreach ($sectors as $s) {
            $conditions[] = 'LOWER(a.sectors) LIKE ?';
            $params[] = '%' . $s . '%';
        }
        foreach ($countries as $c) {
            $conditions[] = 'a.countries LIKE ?';
            $params[] = '%' . $c . '%';
        }
        if ($conditions === []) {
            return ['bands' => [], 'sample' => 0, 'min_sample' => $minSample];
        }
        $rows = Db::all(
            'SELECT a.amount, a.currency FROM awards a
             WHERE a.amount IS NOT NULL AND a.amount > 0
               AND a.currency IS NOT NULL AND a.currency <> \'\'
               AND (' . implode(' OR ', $conditions) . ')',
            $params
        );

        $groups = [];
        foreach ($rows as $row) {
            $currency = strtoupper((string)$row['currency']);
            $groups[$currency][] = (float)$row['amount'];
        }

        $bands = [];
        foreach ($groups as $currency => $amounts) {
            if (count($amounts) < $minSample) {
                continue;
            }
            sort($amounts);
            $n = count($amounts);
            $band = [
                'currency' => $currency,
                'count' => $n,
                'min' => (float)$amounts[0],
                'p25' => self::quantile($amounts, 0.25),
                'median' => self::quantile($amounts, 0.50),
                'p75' => self::quantile($amounts, 0.75),
                'max' => (float)$amounts[$n - 1],
                'your_value' => null,
                'your_percentile' => null,
            ];
            if ($yourValue !== null && $yourCurrency !== '' && $yourCurrency === $currency) {
                $below = count(array_filter($amounts, static fn($x) => $x <= $yourValue));
                $band['your_value'] = $yourValue;
                $band['your_percentile'] = (int)round(100 * $below / $n);
            }
            $bands[] = $band;
        }
        usort($bands, static fn($a, $b) => $b['count'] <=> $a['count']);
        return ['bands' => $bands, 'sample' => count($rows), 'min_sample' => $minSample];
    }

    /** Nearest-rank quantile on an ascending list. */
    private static function quantile(array $sorted, float $p): float
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }
        if ($n === 1) {
            return (float)$sorted[0];
        }
        $rank = (int)ceil($p * $n) - 1;
        $rank = max(0, min($n - 1, $rank));
        return (float)$sorted[$rank];
    }

    private static function score(array $award, array $sectors, array $countries, array $tokens): int
    {
        $score = 0;
        $awardSectors = self::lower(json_list($award['sectors'] ?? []));
        $awardCountries = json_list($award['countries'] ?? []);
        foreach ($sectors as $s) {
            if (in_array($s, $awardSectors, true)) {
                $score += 3;
            }
        }
        foreach ($countries as $c) {
            if (in_array($c, $awardCountries, true)) {
                $score += 2;
            }
        }
        $haystack = mb_strtolower(
            ($award['title'] ?? '') . ' ' . ($award['search_text'] ?? '') . ' ' .
            ($award['winner'] ?? '') . ' ' . ($award['buyer'] ?? '')
        );
        $hits = 0;
        foreach ($tokens as $token) {
            if ($token !== '' && str_contains($haystack, $token)) {
                $hits++;
                if ($hits >= 6) {
                    break;
                }
            }
        }
        $score += $hits;
        if ((float)($award['amount'] ?? 0) > 0) {
            $score++;
        }
        return $score;
    }

    /** @return array{0:?float,1:?string} */
    private static function money(array $a): array
    {
        $amount = null;
        foreach (['amount', 'value_amount', 'award_amount', 'contract_amount', 'total_amount'] as $k) {
            if (isset($a[$k]) && is_numeric($a[$k])) {
                $amount = (float)$a[$k];
                break;
            }
        }
        $currency = null;
        foreach (['currency', 'value_currency', 'amount_currency'] as $k) {
            if (!empty($a[$k])) {
                $currency = strtoupper((string)$a[$k]);
                break;
            }
        }
        if ($amount === null) {
            foreach (['amount_raw', 'value', 'award_value', 'contract_value'] as $k) {
                if (isset($a[$k]) && is_string($a[$k]) && trim($a[$k]) !== '') {
                    [$parsed, $parsedCurrency] = Normalizer::money($a[$k]);
                    if ($parsed !== null) {
                        $amount = $parsed;
                        $currency = $currency ?? $parsedCurrency;
                        break;
                    }
                }
            }
        }
        return [$amount, $currency];
    }

    /** @return list<string> */
    private static function tokens(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];
        $out = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 4 || is_numeric($word) || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            if (!in_array($word, $out, true)) {
                $out[] = $word;
            }
        }
        return $out;
    }

    /** @return list<string> */
    private static function lower(array $values): array
    {
        return array_values(array_map(static fn($v) => mb_strtolower((string)$v), $values));
    }
}
