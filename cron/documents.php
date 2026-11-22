<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';

use Tendari\Core\Db;
use Tendari\Models\TenderFile;
use Tendari\Services\DocumentFetcher;

Db::migrate();

$opts = getopt('', ['tender::', 'limit::']);
$limit = max(1, min(50, (int)($opts['limit'] ?? 12)));

/**
 * Discover and store documents for one tender.
 * Returns [queued, downloaded, snapshot].
 */
function process_tender(int $tenderId): array
{
    $tender = Db::one('SELECT id, source_url FROM tenders WHERE id = ?', [$tenderId]);
    if ($tender === null) {
        return [0, 0, false];
    }
    $queued = 0;
    if (!TenderFile::hasReady($tenderId)) {
        $queued = DocumentFetcher::extractFromPage($tenderId, (string)$tender['source_url']);
    }
    $downloaded = DocumentFetcher::downloadPendingFor($tenderId, 5);
    $snapshot = false;
    if (!TenderFile::hasReady($tenderId)) {
        $snapshot = DocumentFetcher::snapshotPage($tenderId, (string)$tender['source_url']);
    }
    return [$queued, $downloaded, $snapshot];
}

if (!empty($opts['tender'])) {
    $tenderId = (int)$opts['tender'];
    [$queued, $downloaded, $snapshot] = process_tender($tenderId);
    printf("tender #%d: queued=%d downloaded=%d snapshot=%s\n", $tenderId, $queued, $downloaded, $snapshot ? 'yes' : 'no');
    exit(0);
}

$stats = ['queued' => 0, 'downloaded' => 0, 'snapshots' => 0, 'failed' => 0];
$startedAt = time();
$timeBudget = 100; // seconds; keeps the cron polite and bounded

// 1. Download anything already queued (documents from connector APIs such as Kenya PPIP).
foreach (DocumentFetcher::globalPending(30) as $file) {
    if (time() - $startedAt > $timeBudget) {
        break;
    }
    if (DocumentFetcher::download((int)$file['id'])) {
        $stats['downloaded']++;
    } else {
        $stats['failed']++;
    }
}

// 2. Discover documents for recent open tenders that have none yet.
$tenders = Db::all(
    "SELECT t.id FROM tenders t
     WHERE t.status = 'open'
       AND t.first_seen_at >= datetime('now', '-45 days')
       AND t.source_url LIKE 'http%'
       AND NOT EXISTS (SELECT 1 FROM tender_files f WHERE f.tender_id = t.id AND f.status IN ('ready', 'pending'))
     ORDER BY t.first_seen_at DESC, t.id DESC
     LIMIT ?",
    [$limit]
);

foreach ($tenders as $row) {
    if (time() - $startedAt > $timeBudget) {
        break;
    }
    $tenderId = (int)$row['id'];
    [$queued, $downloaded, $snapshot] = process_tender($tenderId);
    $stats['queued'] += $queued;
    $stats['downloaded'] += $downloaded;
    if ($snapshot) {
        $stats['snapshots']++;
    }
    usleep(400_000); // stay polite
}

printf(
    "documents: queued=%d downloaded=%d snapshots=%d failed=%d (tenders scanned=%d)\n",
    $stats['queued'], $stats['downloaded'], $stats['snapshots'], $stats['failed'], count($tenders)
);
