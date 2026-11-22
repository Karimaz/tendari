<?php
declare(strict_types=1);

namespace Tendari\Services;

use Tendari\Core\Db;

/** Builds DB queries for /tenders (FTS MATCH with LIKE fallback) and returns page results. */
final class SearchService
{
    public static function search(array $filters, int $limit = 20, ?int $cursor = null): array
    {
        $where = [];
        $params = [];
        $ftsOnly = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $match = self::ftsEscape($q);
            $ftsOnly = [$match];
        }

        if (($filters['status'] ?? '') !== '' && in_array($filters['status'], ['open', 'closed', 'awarded', 'cancelled'], true)) {
            $where[] = 't.status = ?';
            $params[] = $filters['status'];
        } else {
            $where[] = "t.status IN ('open','closed')"; // hide awarded/cancelled by default
        }

        $countries = json_list($filters['countries'] ?? []);
        if ($countries !== []) {
            $or = [];
            foreach ($countries as $c) {
                $or[] = 't.countries LIKE ?';
                $params[] = '%' . strtoupper($c) . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }

        $funding = (string)($filters['funding_type'] ?? '');
        if ($funding !== '' && in_array($funding, ['grant', 'procurement', 'loan', 'framework', 'unknown'], true)) {
            $where[] = 't.funding_type = ?';
            $params[] = $funding;
        }

        $sectors = json_list($filters['sectors'] ?? []);
        if ($sectors !== []) {
            $or = [];
            foreach ($sectors as $s) {
                $or[] = '(t.search_text LIKE ? OR t.sectors LIKE ?)';
                $params[] = '%' . mb_strtolower($s) . '%';
                $params[] = '%' . $s . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }

        $within = (int)($filters['within_days'] ?? 0);
        if ($within > 0) {
            $where[] = "t.deadline_at IS NOT NULL AND t.deadline_at >= datetime('now')
                        AND t.deadline_at <= datetime('now', '+' || ? || ' days')";
            $params[] = $within;
        }

        $from = trim((string)($filters['deadline_from'] ?? ''));
        $to = trim((string)($filters['deadline_to'] ?? ''));
        if ($from !== '') {
            $d = Normalizer::date($from);
            if ($d) {
                $where[] = 't.deadline_at >= ?';
                $params[] = $d;
            }
        }
        if ($to !== '') {
            $d = Normalizer::date($to);
            if ($d) {
                $where[] = 't.deadline_at <= ?';
                $params[] = $d;
            }
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $cursorSql = $cursor !== null ? ' AND t.id < ' . (int)$cursor : '';

        // Try FTS first when a keyword is present
        if ($ftsOnly !== []) {
            try {
                $sql = 'SELECT t.* FROM tenders t JOIN tenders_fts f ON f.rowid = t.id
                        WHERE tenders_fts MATCH ? ' . ($whereSql ? 'AND ' . preg_replace('/^WHERE /', '', $whereSql) : '')
                      . $cursorSql . ' ORDER BY t.first_seen_at DESC, t.id DESC LIMIT ' . (int)$limit;
                $rows = Db::all($sql, array_merge($ftsOnly, $params));
                if ($rows !== []) {
                    return $rows;
                }
                // fall through to LIKE
            } catch (\Throwable) {
                // FTS syntax issue -> LIKE fallback below
            }
        }

        $like = [];
        $likeParams = [];
        if ($q !== '') {
            foreach (preg_split('/\s+/', mb_strtolower($q)) ?: [] as $term) {
                if ($term === '') {
                    continue;
                }
                $like[] = 't.search_text LIKE ?';
                $likeParams[] = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
            }
            if ($like === []) {
                $like[] = '0 = 1';
            }
        }
        $conditions = [];
        if ($whereSql !== '') {
            $conditions[] = substr($whereSql, 6);
        }
        if ($like !== []) {
            $conditions[] = implode(' AND ', $like);
        }
        $sql = 'SELECT t.* FROM tenders t '
             . ($conditions ? 'WHERE ' . implode(' AND ', $conditions) : '')
             . $cursorSql
             . ' ORDER BY t.first_seen_at DESC, t.id DESC LIMIT ' . (int)$limit;
        return Db::all($sql, array_merge($params, $likeParams));
    }

    public static function ftsEscape(string $q): string
    {
        $terms = preg_split('/\s+/', trim($q)) ?: [];
        $out = [];
        foreach ($terms as $t) {
            $out[] = '"' . str_replace('"', ' ', $t) . '"';
        }
        return implode(' AND ', $out);
    }
}
