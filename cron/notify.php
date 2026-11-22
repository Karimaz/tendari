<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';
\Tendari\Core\Db::migrate();

$summary = \Tendari\Services\NotifyService::run();
printf("new_tenders=%d email_queued=%d telegram_queued=%d dispatched=%d\n",
    $summary['new_tenders'], $summary['email_queued'], $summary['telegram_queued'], $summary['dispatched']);
