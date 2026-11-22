<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';

echo "Backfilling awards from awarded notices...\n";
$result = \Tendari\Services\AwardService::backfillFromAwardedTenders();
echo "  scanned: {$result['scanned']}\n";
echo "  awards recorded: {$result['awards']}\n";
echo "Done.\n";
