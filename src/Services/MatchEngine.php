<?php
declare(strict_types=1);

namespace Tendari\Services;

use Tendari\Core\Db;

/** Saved-search vs tender matching (PHP-side; used by MatchEngine queries + FTS). */
final class MatchEngine
{
    /**
     * Does a normalized tender array match a saved-search row?
     * $tender: array with keys title, description, organization, search_text, countries, sectors,
     *          funding_type, deadline_at, status.
     */
    public static function matches(array $search, array $tender): bool
    {
        $haystack = mb_strtolower((string)($tender['search_text'] ?? ''));

        $keywords = self::splitTerms((string)($search['keywords'] ?? ''));
        foreach ($keywords as $kw) {
            if ($haystack === '' || !str_contains($haystack, mb_strtolower($kw))) {
                return false;
            }
        }

        $exclude = self::splitTerms((string)($search['exclude_keywords'] ?? ''));
        foreach ($exclude as $kw) {
            if ($haystack !== '' && str_contains($haystack, mb_strtolower($kw))) {
                return false;
            }
        }

        $searchCountries = json_list($search['countries'] ?? '');
        if ($searchCountries !== []) {
            $tenderCountries = json_list($tender['countries'] ?? '');
            if (array_intersect(array_map('strtoupper', $searchCountries), $tenderCountries) === []) {
                return false;
            }
        }

        $searchSectors = json_list($search['sectors'] ?? '');
        if ($searchSectors !== []) {
            $tenderSectors = json_list($tender['sectors'] ?? '');
            $hayAll = $haystack;
            $ok = false;
            foreach ($searchSectors as $sector) {
                if (in_array(mb_strtolower($sector), array_map('mb_strtolower', $tenderSectors), true)
                    || str_contains($hayAll, mb_strtolower((string)$sector))) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return false;
            }
        }

        $searchTypes = json_list($search['funding_types'] ?? '');
        if ($searchTypes !== []) {
            $ft = mb_strtolower((string)($tender['funding_type'] ?? 'unknown'));
            if (!in_array($ft, array_map('mb_strtolower', $searchTypes), true)) {
                return false;
            }
        }

        $days = $search['deadline_within_days'] ?? null;
        if ($days !== null && $days !== '') {
            if (($tender['status'] ?? '') !== 'open' || empty($tender['deadline_at'])) {
                return false;
            }
            $deadline = strtotime((string)$tender['deadline_at']);
            if ($deadline === false || $deadline > time() + ((int)$days * 86400)) {
                return false;
            }
        }

        return true;
    }

    private static function splitTerms(string $raw): array
    {
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return [];
        }
        // support quoted phrases + space separation (AND semantics)
        preg_match_all('/"([^"]+)"|(\S+)/', $raw, $m);
        $terms = array_filter(array_merge($m[1], $m[2]), fn($v) => trim((string)$v) !== '');
        return array_values(array_map('strval', $terms));
    }

    /** Tenders that match one search row, pulling candidate set from the DB. */
    public static function searchMatches(array $search, int $limit = 100): array
    {
        $conditions = [];
        $params = [];

        $keywords = self::splitTerms((string)($search['keywords'] ?? ''));
        foreach ($keywords as $kw) {
            $conditions[] = 'search_text LIKE ?';
            $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($kw)) . '%';
        }
        foreach (self::splitTerms((string)($search['exclude_keywords'] ?? '')) as $kw) {
            $conditions[] = 'search_text NOT LIKE ?';
            $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($kw)) . '%';
        }
        $countries = json_list($search['countries'] ?? '');
        if ($countries !== []) {
            $or = [];
            foreach ($countries as $c) {
                $or[] = 'countries LIKE ?';
                $params[] = '%' . strtoupper($c) . '%';
            }
            $conditions[] = '(' . implode(' OR ', $or) . ')';
        }
        $types = json_list($search['funding_types'] ?? '');
        if ($types !== []) {
            $or = [];
            foreach ($types as $t) {
                $or[] = 'funding_type = ?';
                $params[] = strtolower($t);
            }
            $conditions[] = '(' . implode(' OR ', $or) . ')';
        }
        $days = $search['deadline_within_days'] ?? null;
        if ($days !== null && $days !== '') {
            $conditions[] = "status = 'open' AND deadline_at IS NOT NULL
                             AND deadline_at <= datetime('now', '+' || ? || ' days')";
            $params[] = (int)$days;
        }

        $sql = 'SELECT * FROM tenders';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY first_seen_at DESC, id DESC LIMIT ' . (int)$limit;
        return Db::all($sql, $params);
    }
}
