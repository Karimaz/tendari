<?php
/**
 * Tendari test runner — plain asserts, CLI only.
 * Usage: php tests/run.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/TestHelpers.php';

use Tendari\Core\Config;
use Tendari\Core\Db;

// isolated temp DB so tests never touch the real one
$tmpDb = sys_get_temp_dir() . '/tenderhunter_test_' . getmypid() . '.sqlite';
@unlink($tmpDb);
Config::set(array_merge(Config::load(), ['db' => ['path' => $tmpDb]]));
Db::migrate();

$tests = 0;
$failed = 0;
$failures = [];

function check(bool $cond, string $name): void
{
    global $tests, $failed, $failures;
    $tests++;
    if ($cond) {
        echo "  ✓ {$name}\n";
    } else {
        $failed++;
        $failures[] = $name;
        echo "  ✗ {$name}\n";
    }
}

$started = microtime(true);

echo "== Normalizer ==\n";
$N = Tendari\Services\Normalizer::class;
check($N::date('2026-01-05') === '2026-01-05 00:00:00', 'date Y-m-d');
check($N::date('05/01/2026') === '2026-01-05 00:00:00', 'date d/m/Y');
check($N::date('05.01.2026') === '2026-01-05 00:00:00', 'date d.m.Y');
check($N::date('January 5, 2026') === '2026-01-05 00:00:00', 'date F j, Y');
check($N::date('garbage') === null, 'date garbage -> null');
check($N::date('') === null, 'date empty -> null');
check($N::date('1767225600') === '2026-01-01 00:00:00', 'date unix ts');
check($N::countries('Kenya, uganda') === ['KE', 'UG'], 'countries names');
check($N::countries(['ke', 'XQ']) === ['KE'], 'countries codes');
check($N::fundingType('Request for Quotation') === 'procurement', 'fundingType procurement');
check($N::fundingType('Grant Agreement') === 'grant', 'fundingType grant');
check($N::fundingType('') === 'unknown', 'fundingType empty');
check($N::textKey('  Hello,   WORLD!! ') === 'hello world', 'textKey');
check($N::money('EUR 1.2 million') === [1200000.0, 'EUR'], 'money EUR million');
check($N::money('$150,000') === [150000.0, 'USD'], 'money USD');
check($N::money('') === [null, null], 'money empty');
check($N::cleanText('<p>Hello &amp;   <b>world</b></p>') === 'Hello & world', 'cleanText');
check($N::status('2026-01-01 00:00:00', '2025-12-01 00:00:00') === 'closed', 'status deadline<published');

echo "== Deduper ==\n";
$srcId = 0;
Db::q("INSERT INTO sources (code, name, connector_class) VALUES ('test', 'Test', 'X')");
$srcId = Db::lastId();
$D = Tendari\Services\Deduper::class;
$rec = fn($o = []) => array_merge([
    'title' => 'Supply of Medical Equipment',
    'url' => 'https://example.org/t/1',
    'organization' => 'Ministry of Health',
    'countries' => ['KE'],
    'sectors' => ['health'],
    'funding_type' => 'procurement',
], $o);
[$id1, $res1] = $D::ingest($srcId, $rec());
check($res1 === 'new', 'ingest new');
[$id2, $res2] = $D::ingest($srcId, $rec(['url' => 'https://example.org/t/1?src=mirror']));
check($res2 === 'updated', 'same url -> merge alias');
check($id1 === $id2, 'url merge keeps id');
check(count(Db::all('SELECT * FROM tender_aliases WHERE tender_id = ?', [$id1])) === 1, 'alias row after same-source merge');
[$id3, $res3] = $D::ingest($srcId, $rec(['url' => 'https://other.org/t/2', 'title' => 'supply   of medical equipment!!', 'organization' => 'ministry of health ']));
check($res3 === 'updated' && $id3 === $id1, 'signature match (normalized title/org)');
[$id4, $res4] = $D::ingest($srcId, $rec(['url' => 'https://other.org/t/3', 'title' => 'Completely different tender', 'organization' => 'Other Org']));
check($res4 === 'new' && $id4 !== $id1, 'different tender -> new');
check(Db::one('SELECT search_text FROM tenders WHERE id = ?', [$id1])['search_text'] !== '', 'search_text built');
$ft = Db::scalar('SELECT COUNT(*) FROM tenders_fts WHERE tenders_fts MATCH ?', ['"medical"']);
check((int)$ft >= 1, 'FTS row synced via trigger');
$rehash = $D::rehashRecent();
check(is_array($rehash), 'rehashRecent runs');

echo "== MatchEngine ==\n";
$M = Tendari\Services\MatchEngine::class;
$t = fn($o = []) => array_merge([
    'title' => 'Water Supply Contract in Kenya',
    'description' => '',
    'organization' => 'Water Board',
    'search_text' => 'water supply contract in kenya water board',
    'countries' => '["KE"]',
    'sectors' => '["water"]',
    'funding_type' => 'procurement',
    'deadline_at' => gmdate('Y-m-d H:i:s', time() + 5 * 86400),
    'status' => 'open',
], $o);
check($M::matches(['keywords' => 'water kenya'], $t()) === true, 'AND keywords match');
check($M::matches(['keywords' => 'water germany'], $t()) === false, 'keyword missing');
check($M::matches(['keywords' => '"supply contract"'], $t()) === true, 'phrase match');
check($M::matches(['keywords' => '', 'exclude_keywords' => 'water'], $t()) === false, 'exclude kills');
check($M::matches(['countries' => ['KE']], $t()) === true, 'country intersect');
check($M::matches(['countries' => ['NG']], $t()) === false, 'country no intersect');
check($M::matches(['countries' => []], $t()) === true, 'empty countries = all');
check($M::matches(['funding_types' => ['procurement']], $t()) === true, 'funding type match');
check($M::matches(['funding_types' => ['grant']], $t()) === false, 'funding type mismatch');
check($M::matches(['deadline_within_days' => 10], $t()) === true, 'deadline within ok');
check($M::matches(['deadline_within_days' => 1], $t()) === false, 'deadline too soon');
check($M::matches(['deadline_within_days' => 10], $t(['status' => 'closed'])) === false, 'closed excluded with deadline filter');
check($M::matches(['keywords' => 'water', 'sectors' => ['health']], $t()) === false, 'sector miss');
check($M::matches(['keywords' => 'water', 'sectors' => ['water']], $t()) === true, 'sector hit');

echo "== SearchService ==\n";
$S = Tendari\Services\SearchService::class;
[, $waterRes] = $D::ingest($srcId, $rec([
    'title' => 'Water Supply Contract in Kenya',
    'url' => 'https://example.org/t/water',
    'organization' => 'Water Board',
    'countries' => ['KE'],
    'sectors' => ['water'],
    'deadline_at' => gmdate('Y-m-d H:i:s', time() + 5 * 86400),
]));
check($waterRes === 'new', 'water tender ingested for search tests');
$rows = $S::search(['q' => 'water kenya'], 10);
check(count($rows) >= 1, 'FTS search finds water kenya');
$rows = $S::search(['q' => 'medical equipment', 'countries' => ['KE']], 10);
check(count($rows) >= 1, 'filtered search');
$rows = $S::search(['within_days' => 30], 10);
check(count($rows) >= 1, 'within_days filter');
$rows = $S::search(['within_days' => 30], 10);
check(count(array_filter($rows, fn($r) => $r['title'] === 'Completely different tender')) === 0, 'no deadline -> excluded from within filter');

echo "== AwardService ==\n";
$AS = Tendari\Services\AwardService::class;
$waterTender = Db::one("SELECT * FROM tenders WHERE title = 'Water Supply Contract in Kenya' LIMIT 1");
check($waterTender !== null, 'water tender available for intelligence');
$amounts = [100000, 200000, 300000, 400000, 500000, 600000];
foreach ($amounts as $i => $amt) {
    $AS::record($srcId, [
        'title' => 'Water treatment plant contract ' . $i,
        'source_url' => 'https://awards.test/water/' . $i,
        'buyer' => 'Water Board',
        'winner' => 'Contractor ' . $i,
        'countries' => ['KE'],
        'sectors' => ['water'],
        'amount' => $amt,
        'currency' => 'KES',
        'awarded_at' => '2026-01-0' . ($i + 1),
    ]);
}
check(\Tendari\Models\Award::count() === 6, 'six awards recorded');
$again = $AS::record($srcId, [
    'title' => 'Water treatment plant contract 0',
    'source_url' => 'https://awards.test/water/0',
    'winner' => 'Contractor Zero',
]);
check($again !== null, 'award re-record returns id');
check((int)Db::scalar("SELECT COUNT(*) FROM awards WHERE source_url = 'https://awards.test/water/0'") === 1, 'award upsert keeps one row');
check(Db::scalar("SELECT winner FROM awards WHERE source_url = 'https://awards.test/water/0'") === 'Contractor Zero', 'award winner filled on upsert');
check((int)Db::scalar('SELECT COUNT(*) FROM awards_fts WHERE awards_fts MATCH ?', ['"treatment"']) === 6, 'awards FTS synced via trigger');

$intel = $AS::intelligence($waterTender, 5);
check(count($intel['similar']) >= 5, 'similar awards found');
check($intel['basis'] === 'sector', 'price basis is sector');
check($intel['bands'] !== [], 'price band produced with enough sample');
check($intel['bands'][0]['currency'] === 'KES', 'band currency KES');
check($intel['bands'][0]['count'] === 6, 'band count matches sample');
check(abs($intel['bands'][0]['p25'] - 200000) < 0.01, 'p25 nearest-rank');
check(abs($intel['bands'][0]['median'] - 300000) < 0.01, 'median nearest-rank');
check(abs($intel['bands'][0]['p75'] - 500000) < 0.01, 'p75 nearest-rank');

[$healthId] = $D::ingest($srcId, $rec([
    'title' => 'Health clinic equipment supplies',
    'url' => 'https://example.org/health-equipment',
    'sectors' => ['health'],
    'countries' => ['NG'],
]));
foreach ([120000, 180000] as $i => $amt) {
    $AS::record($srcId, [
        'title' => 'Health clinic equipment award ' . $i,
        'source_url' => 'https://awards.test/health/' . $i,
        'countries' => ['NG'],
        'sectors' => ['health'],
        'amount' => $amt,
        'currency' => 'NGN',
    ]);
}
$healthIntel = $AS::intelligence(Db::one('SELECT * FROM tenders WHERE id = ?', [$healthId]), 5);
check($healthIntel['bands'] === [], 'below threshold -> no price band');
check($healthIntel['sample'] === 2, 'sample still reported below threshold');

[$awardTenderId] = $D::ingest($srcId, $rec([
    'title' => 'Awarded road rehabilitation works',
    'url' => 'https://example.org/awarded-road',
    'sectors' => ['transport'],
    'countries' => ['KE'],
]));
Db::q("UPDATE tenders SET status = 'awarded', value_amount = 500000, value_currency = 'KES' WHERE id = ?", [$awardTenderId]);
$backfill = $AS::backfillFromAwardedTenders();
check($backfill['awards'] >= 1, 'backfill mirrors awarded notices into awards');
check((int)Db::scalar('SELECT COUNT(*) FROM awards WHERE tender_id = ?', [$awardTenderId]) === 1, 'award linked to its tender');

echo "== IntelligenceAccess ==\n";
$IA = Tendari\Services\IntelligenceAccess::class;
check($IA::status(null, '2026-03')['plan'] === 'guest', 'guest has no intelligence');
Tendari\Core\Auth::register('intel@example.org', 'password1', 'Intel');
$intelUser = Tendari\Models\User::byEmail('intel@example.org');
$uid = (int)$intelUser['id'];
$free = $IA::status($uid, '2026-03');
check($free['plan'] === 'free' && $free['limit'] === 0, 'free plan has no intelligence');
check($IA::canView($uid, (int)$waterTender['id'], '2026-03')['allowed'] === false, 'free denied');
check($IA::unlock($uid, (int)$waterTender['id'], '2026-03')['allowed'] === false, 'free cannot consume');

Db::q("INSERT INTO subscriptions (user_id, plan, status) VALUES (?,?, 'active')", [$uid, 'starter']);
$starter = $IA::status($uid, '2026-03');
check($starter['plan'] === 'starter' && $starter['limit'] === 5, 'starter limit is 5');
$ids = [];
for ($i = 0; $i < 6; $i++) {
    Db::q(
        "INSERT INTO tenders (canonical_id, title, source_id, source_url, status) VALUES (?,?,?,?, 'open')",
        ['intel-test-' . $i, 'Intelligence test tender ' . $i, $srcId, 'https://example.org/intel-test/' . $i]
    );
    $ids[] = Db::lastId();
}
$allowed = 0;
foreach (array_slice($ids, 0, 5) as $id) {
    if ($IA::unlock($uid, (int)$id, '2026-03')['allowed']) {
        $allowed++;
    }
}
check($allowed === 5, 'starter allows five tender views');
$blocked = $IA::unlock($uid, (int)$ids[5], '2026-03');
check($blocked['allowed'] === false && $blocked['reason'] === 'exhausted', 'sixth view blocked');
check($IA::unlock($uid, (int)$ids[0], '2026-03')['allowed'] === true, 'revisit does not consume quota');
check($IA::unlock($uid, (int)$ids[5], '2026-04')['allowed'] === true, 'new month resets quota');

Db::q("INSERT INTO subscriptions (user_id, plan, status) VALUES (?,?, 'active')", [$uid, 'pro']);
check($IA::status($uid, '2026-03')['limit'] === 15, 'pro limit is 15');
Db::q("INSERT INTO subscriptions (user_id, plan, status) VALUES (?,?, 'active')", [$uid, 'business']);
$biz = $IA::status($uid, '2026-03');
check($biz['unlimited'] === true && $biz['remaining'] === null, 'business is unlimited');
check($IA::unlock($uid, (int)$ids[5], '2026-03')['allowed'] === true, 'business can view');
check($IA::status($uid, '2026-03', true)['unlimited'] === true, 'admin bypass is unlimited');

echo "== Auth ==\n";
$hash = password_hash('secret123', PASSWORD_DEFAULT);
check(password_verify('secret123', $hash), 'password hash/verify');
check(Tendari\Core\Auth::csrfToken() !== '', 'csrf token generated');
check(!password_verify('wrong', $hash), 'wrong password rejected');
// rate limiter
$rlClass = new ReflectionClass('Tendari\Core\RateLimiter');
$rlMethod = $rlClass->getMethod('path');
$rlKey = 'login:test:ip:' . uniqid();
check(Tendari\Core\RateLimiter::allow($rlKey, 2, 60) === true, 'rate limiter 1st ok');
check(Tendari\Core\RateLimiter::allow($rlKey, 2, 60) === true, 'rate limiter 2nd ok');
check(Tendari\Core\RateLimiter::allow($rlKey, 2, 60) === false, 'rate limiter 3rd blocked');
Tendari\Core\RateLimiter::clear($rlKey);
check(Tendari\Core\RateLimiter::allow($rlKey, 2, 60) === true, 'rate limiter cleared');

echo "== Registration flow (DB) ==\n";
$reg = Tendari\Core\Auth::register('alice@example.org', 'password1', 'Alice');
check($reg['ok'] === true, 'register ok');
check(Tendari\Models\User::byEmail('ALICE@example.org')['id'] === Tendari\Models\User::byEmail('alice@example.org')['id'], 'email NOCASE lookup');
$reg2 = Tendari\Core\Auth::register('alice@example.org', 'password1', 'Alice');
check($reg2['ok'] === false, 'duplicate email rejected');
check(Tendari\Models\User::notifyChannels(['email_notifications' => 1, 'email_verified' => 1, 'telegram_verified' => 1]) === [], 'channels gated on smtp/tg config');

echo "== Notifiers ==\n";
$TG = Tendari\Notifiers\TelegramNotifier::class;
check($TG::escapeMarkdownV2('Hello *world!* (x)') === 'Hello \*world\!\* \(x\)', 'MarkdownV2 escape');
check($TG::escapeUrl('https://x.org/a)b') === 'https://x.org/a\\)b', 'URL escape');
$email = new Tendari\Notifiers\EmailNotifier();
check($email->send('x@example.org', 't', '<b>hi</b>') === false, 'email without SMTP fails gracefully');
check(Tendari\Models\Notification::pending() === [], 'pending empty initially');

echo "== HealthChecker.evaluate ==\n";
$H = Tendari\Services\HealthChecker::class;
check($H::evaluate(200, '{"rows":1,"procnotices":[]}', true)['status'] === 'ok', 'evaluate: valid JSON -> ok');
check($H::evaluate(200, '{"status":"error","message":"boom"}', true)['status'] === 'changed', 'evaluate: error document -> changed');
check($H::evaluate(200, '<html>oops</html>', true)['status'] === 'changed', 'evaluate: HTML where JSON expected -> changed');
check($H::evaluate(500, 'oops', true)['status'] === 'down', 'evaluate: HTTP 500 -> down');
check($H::evaluate(405, '{"message":"POST only"}', true)['status'] === 'ok', 'evaluate: 405 POST-only -> ok');
check($H::evaluate(200, '<html>page</html>', false)['status'] === 'ok', 'evaluate: HTML page connector -> ok');
$truncated = substr(str_repeat('{"data":', 2000), 0, 4000); // truncated large JSON
check($H::evaluate(200, $truncated, true)['status'] === 'ok', 'evaluate: truncated JSON head -> ok (not changed)');
check($H::evaluate(200, '{"status":403,"error":{"message":"appname not approved"}}', true)['status'] === 'changed', 'evaluate: int error status -> changed');

echo "== Connector normalizers (offline fixtures) ==\n";
$wb = new Tendari\Connectors\Api\WorldBank();
$wbRaw = json_decode((string)file_get_contents(__DIR__ . '/fixtures/worldbank.json'), true);
$wbDocs = $wbRaw['procnotices'];
$wbNorm = $wb->normalize($wbDocs[0]);
check($wbNorm !== null && str_contains($wbNorm['title'], 'irrigation schemes'), 'worldbank title from bid_description');
check($wbNorm['countries'] === ['ET'], 'worldbank country name -> ISO2');
check($wbNorm['funding_type'] === 'framework', 'worldbank REOI -> framework');
check($wbNorm['deadline_at'] === '2026-11-30 00:00:00', 'worldbank deadline ISO8601');
check($wb->normalize($wbDocs[1])['funding_type'] === 'procurement', 'worldbank IFB -> procurement');
check($wb->normalize($wbDocs[2])['status'] === 'awarded', 'worldbank award -> awarded');
$wbAward = $wb->normalizeAward($wbDocs[2]);
check($wbAward !== null && $wbAward['title'] !== '', 'worldbank award normalized');
check($wb->normalizeAward($wbDocs[0]) === null, 'worldbank non-award -> null award');

$rw = new Tendari\Connectors\Api\ReliefWeb();
$rwRaw = json_decode((string)file_get_contents(__DIR__ . '/fixtures/reliefweb.json'), true);
$rwNorm = $rw->normalize($rwRaw['data'][0]['fields'] + ['_resource' => 'jobs']);
check($rwNorm !== null && $rwNorm['countries'] === ['SO'], 'reliefweb iso3 -> iso2');
check($rwNorm['organization'] === 'UNICEF', 'reliefweb org');
check($rwNorm['funding_type'] === 'framework', 'reliefweb jobs -> framework');
check($rw->normalize($rwRaw['data'][1]['fields'] + ['_resource' => 'training'])['funding_type'] === 'unknown', 'training -> unknown');

$ted = new Tendari\Connectors\Api\Ted();
$tedRaw = json_decode((string)file_get_contents(__DIR__ . '/fixtures/ted.json'), true);
$tedNorm = $ted->normalize($tedRaw['notices'][0]);
check($tedNorm !== null && $tedNorm['countries'] === ['MA'], 'ted country');
check($tedNorm['url'] !== '' && $tedNorm['title'] !== '', 'ted url+title');
check($tedNorm['value_amount'] === 2400000.0 && $tedNorm['value_currency'] === 'EUR', 'ted value');

// HTML fixture parsers
$ebrdHtml = (string)file_get_contents(__DIR__ . '/fixtures/ebrd.html');
$ebrd = new Tendari\Connectors\Web\Ebrd();
$rows = $ebrd->normalize(['_cells' => ['Consulting Services for Solar Grid Extension', 'Consultant', 'Jordan', '25 Dec 2026'],
                          '_href' => '/what-we-do/procurement/notices/12345']);
check($rows !== null && $rows['countries'] === ['JO'], 'ebrd country parse');
check($rows['deadline_at'] === '2026-12-25 00:00:00', 'ebrd deadline parse');
check(str_contains($rows['url'], 'ebrd.com/home'), 'ebrd url join');

$za = new Tendari\Connectors\Web\ZaEtenders();
$zaHtml = (string)file_get_contents(__DIR__ . '/fixtures/za_etenders.html');
$doc = new DOMDocument();
libxml_use_internal_errors(true);
$doc->loadHTML($zaHtml);
libxml_clear_errors();
$tr = $doc->getElementsByTagName('tr')->item(1);
$cells = [];
foreach ($tr->getElementsByTagName('td') as $td) {
    $cells[] = trim($td->textContent);
}
$zaNorm = $za->normalize(['_cells' => $cells, '_href' => '/tender/2026/ABC001']);
check($zaNorm !== null && $zaNorm['countries'] === ['ZA'], 'za country');
check($zaNorm['deadline_at'] === '2026-12-05 00:00:00', 'za deadline');
check(str_contains($zaNorm['url'], 'etenders.gov.za'), 'za url join');

// registration stub throws
$stub = new Tendari\Connectors\Web\RegistrationConnector([], 'sa_etimad');
try {
    $stub->fetch();
    check(false, 'registration stub throws');
} catch (Tendari\Connectors\RegistrationRequiredException $e) {
    check(true, 'registration stub throws');
}

echo "== Verification ==\n";
$V = Tendari\Services\Verification::class;
$alice = Tendari\Models\User::byEmail('alice@example.org');
$tok = $V::telegramLinkToken((int)$alice['id']);
check(Tendari\Core\Db::one('SELECT * FROM telegram_link_tokens WHERE token = ?', [$tok]) !== null, 'tg link token stored');
$found = $V::consumeTelegramToken($tok);
check($found !== null && (int)$found['id'] === (int)$alice['id'], 'tg token consumed');
check($V::consumeTelegramToken($tok) === null, 'tg token single-use');
Tendari\Models\User::linkTelegram((int)$alice['id'], '12345');
check((int)Tendari\Models\User::find((int)$alice['id'])['telegram_verified'] === 1, 'telegram linked');

echo "== Notify pipeline (dry) ==\n";
$searchId = Tendari\Models\SavedSearch::create((int)$alice['id'], [
    'name' => 'Water KE', 'keywords' => 'water', 'exclude_keywords' => '',
    'countries' => ['KE'], 'sectors' => [], 'funding_types' => [], 'deadline_within_days' => null, 'active' => 1,
]);
$matches = Tendari\Services\MatchEngine::searchMatches(Tendari\Models\SavedSearch::find($searchId), 10);
check(count($matches) >= 1, 'test-run search finds water tender');
$summary = Tendari\Services\NotifyService::run();
check($summary['new_tenders'] >= 3, 'notify run sees seeded tenders');

// cleanup
@unlink($tmpDb);

echo "\n" . str_repeat('-', 50) . "\n";
printf("Tests: %d, Failed: %d (%.0fms)\n", $tests, $failed, (microtime(true) - $started) * 1000);
if ($failed > 0) {
    foreach ($failures as $f) {
        echo "FAILED: {$f}\n";
    }
    exit(1);
}
echo "All tests passed ✅\n";
