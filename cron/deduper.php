<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';
\Tendari\Core\Db::migrate();

$stats = \Tendari\Services\Deduper::rehashRecent();
printf("scanned=%d merged=%d\n", $stats['scanned'], $stats['merged']);

// close tenders past their deadline
\Tendari\Core\Db::q("UPDATE tenders SET status = 'closed', updated_at = datetime('now')
                          WHERE status = 'open' AND deadline_at IS NOT NULL AND deadline_at < datetime('now')");
