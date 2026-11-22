<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';

echo "Applying migrations...\n";
$ran = \Tendari\Core\Db::migrate();
echo $ran ? implode("\n", array_map(fn($n) => "  ✓ {$n}", $ran)) : "  (nothing to do — schema up to date)\n";
echo "Done.\n";
