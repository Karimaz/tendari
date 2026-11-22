<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';
\Tendari\Core\Db::migrate();

$count = \Tendari\Services\NotifyService::runDigest();
printf("digest emails sent=%d\n", $count);
