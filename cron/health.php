<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';
\Tendari\Core\Db::migrate();

$results = \Tendari\Services\HealthChecker::probeAll();
$problems = 0;

printf("%-14s %-18s %-6s %-10s %s\n", 'SOURCE', 'VERDICT', 'HTTP', 'LATENCY', 'MESSAGE');
foreach ($results as $r) {
    printf("%-14s %-18s %-6s %-10s %s\n",
        $r['source'],
        $r['verdict'],
        $r['http_code'] ?? '-',
        $r['latency_ms'] !== null ? $r['latency_ms'] . 'ms' : '-',
        mb_substr($r['message'], 0, 70));
    if (in_array($r['verdict'], ['down', 'changed', 'error'], true)) {
        $problems++;
    }
}

printf("\nchecked_at=%s problems=%d\n", now_utc(), $problems);
if ($problems > 0) {
    logger('warning', "Health cron found {$problems} problematic source(s)");
    exit(1); // non-zero so external monitors can alert
}
