<?php
declare(strict_types=1);

namespace Tendari\Services;

use Tendari\Core\Db;

/**
 * Cross-source dedup + ingest.
 *
 * canonical_id generation:
 *  1. If a non-empty url already exists on another tender -> merge (same canonical, alias row).
 *  2. Otherwise signature = md5(textKey(title) | deadline date | substr(textKey(org),0,24)).
 */
final class Deduper
{
    /** Ingest one normalized record. Returns [tenderId, 'new'|'updated'|'dup']. */
    public static function ingest(int $sourceId, array $rec): array
    {
        if (empty($rec['title']) || empty($rec['url'])) {
            throw new \InvalidArgumentException('Normalized record requires title and url');
        }

        $url = trim((string)$rec['url']);
        $title = Normalizer::title((string)$rec['title']);
        $org = Normalizer::textKey((string)($rec['organization'] ?? ''));
        $deadline = isset($rec['deadline_at']) ? Normalizer::date($rec['deadline_at']) : null;
        $published = isset($rec['published_at']) ? Normalizer::date($rec['published_at']) : null;

        $signature = md5(
            Normalizer::textKey($title) . '|' . ($deadline !== null ? substr($deadline, 0, 10) : 'none') .
            '|' . mb_substr($org, 0, 24)
        );

        // 1. URL match?
        $existing = Db::one('SELECT * FROM tenders WHERE source_url = ? OR canonical_id = ?', [$url, $signature]);
        if (!$existing) {
            // 2. signature match?
            $existing = Db::one('SELECT * FROM tenders WHERE canonical_id = ?', [$signature]);
        }

        $countries = json_encode(Normalizer::countries($rec['countries'] ?? []));
        $sectors = json_encode(json_list($rec['sectors'] ?? []));
        $funding = (string)($rec['funding_type'] ?? 'unknown');
        $description = (string)($rec['description'] ?? '');
        $status = Normalizer::status($published ?? '', $deadline);
        $sourceRef = $rec['source_ref'] ?? null;
        $valueAmount = isset($rec['value_amount']) && is_numeric($rec['value_amount']) ? (float)$rec['value_amount'] : null;
        $valueCurrency = $rec['value_currency'] ?? null;

        if ($existing) {
            $tenderId = (int)$existing['id'];
            // merge: keep earliest first_seen_at, prefer newer/longer description
            $descUpdate = mb_strlen($description) > mb_strlen((string)$existing['description']);
            Db::q(
                'UPDATE tenders SET title = ?, description = ?, organization = ?, countries = ?, sectors = ?,
                        funding_type = ?, value_amount = COALESCE(?, value_amount),
                        value_currency = COALESCE(?, value_currency),
                        published_at = COALESCE(?, published_at), deadline_at = COALESCE(?, deadline_at),
                        status = ?, source_ref = COALESCE(?, source_ref), updated_at = datetime(\'now\')
                 WHERE id = ?',
                [
                    $descUpdate || trim((string)$existing['title']) === '' ? $title : $existing['title'],
                    $descUpdate ? $description : $existing['description'],
                    $descUpdate && (string)$existing['organization'] === '' ? trim((string)($rec['organization'] ?? '')) : $existing['organization'],
                    json_encode(array_values(array_unique(array_merge(json_list($existing['countries']), json_decode($countries, true) ?: [])))),
                    json_encode(array_values(array_unique(array_merge(json_list($existing['sectors']), json_decode($sectors, true) ?: [])))),
                    $funding !== 'unknown' ? $funding : $existing['funding_type'],
                    $valueAmount, $valueCurrency, $published, $deadline,
                    $status,
                    $sourceRef,
                    $tenderId,
                ]
            );
            Db::q(
                'INSERT OR IGNORE INTO tender_aliases (tender_id, source_id, source_ref, url) VALUES (?,?,?,?)',
                [$tenderId, $sourceId, $sourceRef, $url]
            );
            return [$tenderId, 'updated'];
        }

        // insert new
        $searchText = Normalizer::textKey($title . ' ' . $description . ' ' . ($rec['organization'] ?? ''));
        Db::q(
            'INSERT INTO tenders (canonical_id, title, description, organization, source_id, source_ref, source_url,
                                  countries, sectors, funding_type, value_amount, value_currency,
                                  published_at, deadline_at, status, raw, search_text)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $signature, $title, $description, trim((string)($rec['organization'] ?? '')), $sourceId, $sourceRef, $url,
                $countries, $sectors, $funding, $valueAmount, $valueCurrency,
                $published, $deadline, $status,
                json_encode($rec, JSON_UNESCAPED_UNICODE), $searchText,
            ]
        );
        $tenderId = Db::lastId();
        Db::q(
            'INSERT OR IGNORE INTO tender_aliases (tender_id, source_id, source_ref, url) VALUES (?,?,?,?)',
            [$tenderId, $sourceId, $sourceRef, $url]
        );
        return [$tenderId, 'new'];
    }

    /** Nightly pass: re-signature last 7 days to catch late-arriving aliases. */
    public static function rehashRecent(): array
    {
        $rows = Db::all(
            "SELECT * FROM tenders WHERE first_seen_at > datetime('now', '-7 days')"
        );
        $merged = 0;
        foreach ($rows as $row) {
            $sig = md5(
                Normalizer::textKey((string)$row['title']) . '|' .
                ($row['deadline_at'] !== null ? substr((string)$row['deadline_at'], 0, 10) : 'none') . '|' .
                mb_substr(Normalizer::textKey((string)$row['organization']), 0, 24)
            );
            if ($sig === $row['canonical_id']) {
                continue;
            }
            $other = Db::one('SELECT id FROM tenders WHERE canonical_id = ? AND id != ?', [$sig, $row['id']]);
            if ($other) {
                self::mergeTenders((int)$row['id'], (int)$other['id']);
                $merged++;
            } else {
                Db::q('UPDATE tenders SET canonical_id = ? WHERE id = ?', [$sig, $row['id']]);
            }
        }
        return ['scanned' => count($rows), 'merged' => $merged];
    }

    /** Move aliases + notifications onto $keepId, delete $dropId. */
    public static function mergeTenders(int $dropId, int $keepId): void
    {
        Db::q('UPDATE tender_aliases SET tender_id = ? WHERE tender_id = ?', [$keepId, $dropId]);
        Db::q(
            'INSERT OR IGNORE INTO notifications (user_id, tender_id, channel, status, sent_at, error)
             SELECT user_id, ?, channel, status, sent_at, error FROM notifications WHERE tender_id = ?',
            [$keepId, $dropId]
        );
        Db::q('DELETE FROM tenders WHERE id = ?', [$dropId]);
    }
}
