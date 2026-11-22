<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';
\Tendari\Core\Db::migrate();

$due = \Tendari\Models\Source::due();
$summary = \Tendari\Connectors\FetchRunner::run($due);

foreach ($summary as $s) {
    printf("%-14s %-6s fetched=%-4d new=%-4d updated=%-4d %s\n",
        $s['code'], $s['status'], $s['fetched'], $s['new'], $s['updated'], $s['error'] ?? '');
}
