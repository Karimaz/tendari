<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// php -S router: serve existing static files directly
if (PHP_SAPI === 'cli-server') {
    $static = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($static)) {
        return false;
    }
}

use Tendari\Core\App;
use Tendari\Core\Auth;
use Tendari\Core\Db;
use Tendari\Models\Notification;
use Tendari\Models\SavedSearch;
use Tendari\Models\Source;
use Tendari\Models\Tender;
use Tendari\Models\TenderFile;
use Tendari\Models\User;
use Tendari\Models\Waitlist;
use Tendari\Services\Billing;
use Tendari\Services\AwardService;
use Tendari\Services\DocumentFetcher;
use Tendari\Services\IntelligenceAccess;
use Tendari\Services\SearchService;
use Tendari\Services\Verification;

Auth::boot();

$app = new App();

/* ------------------------------------------------------------------ *
 * Health probe (no auth) — used by monitoring / deploy checks
 * ------------------------------------------------------------------ */
$app->get('/health', function () {
    try {
        $tenders = (int)Db::scalar('SELECT COUNT(*) FROM tenders');
        $sources = (int)Db::scalar('SELECT COUNT(*) FROM sources');
        json_out([
            'status' => 'ok',
            'tenders' => $tenders,
            'sources' => $sources,
            'time' => gmdate('c'),
        ]);
    } catch (\Throwable $e) {
        json_out(['status' => 'error', 'message' => 'database unavailable'], 503);
    }
});

/* ------------------------------------------------------------------ *
 * Language switch (cookie + back to previous page)
 * ------------------------------------------------------------------ */
$app->get('/lang/{code}', function ($params) {
    $code = in_array((string)$params['code'], \Tendari\Core\I18n::SUPPORTED, true) ? (string)$params['code'] : 'en';
    setcookie('td_lang', $code, [
        'expires' => time() + 31536000,
        'path' => '/',
        'httponly' => false,
        'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
    ]);
    $back = (string)($_SERVER['HTTP_REFERER'] ?? '/');
    $host = parse_url($back, PHP_URL_HOST);
    $selfHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== null && $host !== $selfHost) {
        $back = '/';
    }
    redirect($back !== '' ? $back : '/');
}, '');

/* ------------------------------------------------------------------ *
 * Public
 * ------------------------------------------------------------------ */

$app->get('/', function () {
    $tenderCount = (int)Db::scalar("SELECT COUNT(*) FROM tenders WHERE status = 'open'");
    $countrySet = [];
    foreach (Db::all('SELECT countries FROM tenders WHERE countries IS NOT NULL') as $row) {
        foreach (json_list($row['countries']) as $c) {
            $c = strtoupper(trim($c));
            if ($c !== '') {
                $countrySet[$c] = true;
            }
        }
    }
    render('pages/home', [
        'title' => t('meta.home'),
        'recent' => $tenderCount > 0 ? Tender::recent(6) : [],
        'stats' => [
            'tenders' => $tenderCount,
            'sources' => (int)Db::scalar('SELECT COUNT(*) FROM sources WHERE enabled = 1'),
            'countries' => count($countrySet),
        ],
        'waitlist_count' => Waitlist::count(),
        'billing_enabled' => Billing::enabled(),
    ]);
});

$app->post('/waitlist', function () {
    keep_old($_POST);
    $v = \Tendari\Core\Validator::make($_POST)
        ->required('email')->email('email')->max('email', 190);
    if ($v->fails()) {
        flash('error', $v->first());
        redirect('/#waitlist');
    }
    $result = Waitlist::add(trim((string)$_POST['email']));
    clear_old();
    flash('success', $result === 'added' ? t('flash.waitlist_added') : t('flash.waitlist_dup'));
    redirect('/#waitlist');
});

$app->get('/pricing', function () {
    render('pages/pricing', [
        'title' => t('nav.pricing'),
        'plans' => Billing::plans(),
        'billing_enabled' => Billing::enabled(),
        'subscription' => Auth::check() ? Billing::subscriptionFor(Auth::id()) : null,
        'canceled' => isset($_GET['canceled']),
    ]);
});

$app->post('/checkout', function () {
    Auth::requireUser();
    $planKey = (string)($_POST['plan'] ?? '');
    $interval = (string)($_POST['interval'] ?? 'month');
    $url = Billing::createCheckout(Auth::id(), $planKey, $interval);
    if ($url === null) {
        flash('error', t('flash.checkout_unavailable'));
        redirect('/pricing');
    }
    redirect($url);
});

$app->get('/billing/success', function () {
    $sessionId = (string)($_GET['session_id'] ?? '');
    if ($sessionId !== '' && Billing::confirmCheckout($sessionId)) {
        flash('success', t('flash.subscription_active'));
        redirect('/dashboard');
    }
    flash('error', t('flash.payment_unconfirmed'));
    redirect('/pricing');
});

$app->get('/billing/cancel', function () {
    redirect('/pricing');
});

$app->post('/stripe/webhook', function () {
    $payload = (string)file_get_contents('php://input');
    $event = Billing::verifyWebhook($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
    if ($event === null) {
        http_response_code(400);
        exit('bad signature');
    }
    Billing::handleWebhookEvent($event);
    http_response_code(200);
    exit('ok');
});

$app->get('/tenders', function () {
    $filters = $_GET;

    // strip empty filter params for clean, shareable URLs
    $cleanFilters = function (array $f): array {
        foreach ($f as $k => $v) {
            if ($k === 'partial' || $k === 'rows_only') { unset($f[$k]); continue; }
            if (is_string($v) && trim($v) === '') { unset($f[$k]); }
        }
        return $f;
    };
    $clean = $cleanFilters($filters);
    if (!is_htmx() && !isset($filters['partial']) && $clean !== $filters) {
        redirect('/tenders' . ($clean ? '?' . http_build_query($clean) : ''));
    }

    $limit = max(20, min(100, (int)($filters['limit'] ?? 20)));
    $cursor = isset($filters['cursor']) ? (int)$filters['cursor'] : null;
    $results = SearchService::search($filters, $limit, $cursor);
    if (isset($filters['partial']) || is_htmx()) {
        if (is_htmx() && !isset($filters['limit']) && !isset($filters['cursor']) && !isset($filters['rows_only'])) {
            header('HX-Push-Url: /tenders' . ($clean ? '?' . http_build_query($clean) : ''));
        }
        render('partials/tender_results', [
            'results' => $results,
            'filters' => $filters,
            'limit' => $limit,
            'cursor' => $cursor,
        ]);
        return;
    }
    render('pages/tenders', ['title' => t('nav.tenders'), 'results' => $results, 'filters' => $filters, 'limit' => $limit, 'cursor' => $cursor]);
});

$app->get('/tenders/{id}', function ($params) {
    $tender = Tender::find((int)$params['id']);
    if ($tender === null) {
        http_response_code(404);
        render('pages/404', ['title' => 'Not found']);
        return;
    }
    $rawIntelligence = AwardService::intelligence($tender);
    $hasIntelligence = $rawIntelligence['similar'] !== [] || $rawIntelligence['bands'] !== [];
    $intelUserId = Auth::id();
    $intelAccess = IntelligenceAccess::status($intelUserId, null, Auth::isAdmin());
    $intelligence = null;
    if ($hasIntelligence) {
        if ($intelUserId !== null) {
            $intelAccess = IntelligenceAccess::unlock($intelUserId, (int)$tender['id'], null, Auth::isAdmin());
            if ($intelAccess['allowed']) {
                $intelligence = $rawIntelligence;
            }
        } else {
            $intelAccess = IntelligenceAccess::canView(null, (int)$tender['id']);
        }
    } else {
        // Nothing to reveal yet: don't consume quota or show an upsell.
        $intelligence = $rawIntelligence;
    }
    render('pages/tender_detail', [
        'title' => $tender['title'],
        'tender' => $tender,
        'aliases' => Tender::aliases((int)$tender['id']),
        'files' => TenderFile::forTender((int)$tender['id']),
        'intelligence' => $intelligence,
        'intel_access' => $intelAccess,
        'intel_has_data' => $hasIntelligence,
    ]);
});

$app->get('/tenders/{id}/files/{fileId}', function ($params) {
    $file = TenderFile::find((int)$params['fileId']);
    if ($file === null || (int)$file['tender_id'] !== (int)$params['id']
        || $file['status'] !== 'ready' || empty($file['storage_path'])) {
        http_response_code(404);
        render('pages/404', ['title' => 'File not found']);
        return;
    }
    $path = DocumentFetcher::absolutePath((string)$file['storage_path']);
    if ($path === null) {
        http_response_code(404);
        render('pages/404', ['title' => 'File not found']);
        return;
    }
    $mime = (string)($file['mime'] ?? 'application/octet-stream');
    $name = (string)($file['filename'] ?? 'document');
    $isPdf = $mime === 'application/pdf';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    header('Content-Disposition: ' . ($isPdf ? 'inline' : 'attachment') . '; filename="' . addslashes($name) . '"');
    if ($mime === 'text/html') {
        // cached source page: render isolated, never execute scripts
        header("Content-Security-Policy: sandbox; default-src 'none'; img-src data:; style-src 'unsafe-inline'");
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
    }
    readfile($path);
    exit;
});

/* ------------------------------------------------------------------ *
 * Auth (guest only)
 * ------------------------------------------------------------------ */

$app->get('/register', function () {
    Auth::requireGuest();
    render('pages/register', ['title' => t('auth.register.submit')]);
});

$app->post('/register', function () {
    Auth::requireGuest();
    keep_old($_POST);
    $v = \Tendari\Core\Validator::make($_POST)
        ->required('email')->email('email')->max('email', 190)
        ->required('password')->min('password', 8)->max('password', 100)
        ->required('name')->max('name', 100);
    if ($v->fails()) {
        flash('error', $v->first());
        redirect('/register');
    }
    $res = Auth::register((string)$_POST['email'], (string)$_POST['password'], (string)$_POST['name']);
    if (!$res['ok']) {
        flash('error', $res['error']);
        redirect('/register');
    }
    clear_old();
    flash('success', t('flash.register_success'));
    redirect('/dashboard');
});

$app->get('/login', function () {
    Auth::requireGuest();
    render('pages/login', ['title' => t('auth.login.submit')]);
});

$app->post('/login', function () {
    Auth::requireGuest();
    $res = Auth::login((string)$_POST['email'], (string)$_POST['password']);
    if (!$res['ok']) {
        flash('error', $res['error']);
        redirect('/login');
    }
    flash('success', t('flash.login_success'));
    redirect('/dashboard');
});

$app->post('/logout', function () {
    Auth::logout();
    redirect('/');
});

$app->get('/verify-email', function ($params, $query = []) {
    $token = (string)($_GET['token'] ?? '');
    $user = Verification::verifyToken($token);
    if ($user === null) {
        flash('error', 'Invalid or expired verification link.');
    } else {
        flash('success', 'Email verified — notifications enabled.');
        if (Auth::check()) {
            Auth::startSession(User::find((int)$user['id']) ?? []);
        }
    }
    redirect(Auth::check() ? '/settings' : '/');
}, '');

/* ------------------------------------------------------------------ *
 * User area
 * ------------------------------------------------------------------ */

$app->get('/dashboard', function () {
    $userId = Auth::id();
    $searches = SavedSearch::forUser($userId);
    foreach ($searches as &$s) {
        $s['match_count'] = count(\Tendari\Services\MatchEngine::searchMatches($s, 51));
    }
    unset($s);
    render('pages/dashboard', [
        'title' => t('nav.dashboard'),
        'searches' => $searches,
        'recent_matches' => Notification::forUser($userId, 10),
    ]);
}, 'user');

/* Saved searches CRUD ------------------------------------------------ */

$app->get('/searches', function () {
    render('pages/search_form', [
        'title' => t('dashboard.new_search'),
        'search' => null,
        'prefill' => $_GET,
    ]);
});

$app->post('/searches', function () {
    $data = self_filter_search($_POST);
    $v = \Tendari\Core\Validator::make($data)->required('name')->max('name', 120);
    if ($v->fails()) {
        flash('error', $v->first());
        redirect('/searches');
    }
    SavedSearch::create(Auth::id(), $data);
    flash('success', 'Search saved.');
    redirect('/dashboard');
});

$app->get('/searches/{id}/edit', function ($params) {
    $search = SavedSearch::forUserOr404((int)$params['id'], Auth::id());
    if ($search === null) {
        flash('error', 'Search not found.');
        redirect('/dashboard');
    }
    render('pages/search_form', ['title' => t('dashboard.edit'), 'search' => $search, 'prefill' => []]);
});

$app->post('/searches/{id}/edit', function ($params) {
    $search = SavedSearch::forUserOr404((int)$params['id'], Auth::id());
    if ($search === null) {
        flash('error', 'Search not found.');
        redirect('/dashboard');
    }
    $data = self_filter_search($_POST);
    $v = \Tendari\Core\Validator::make($data)->required('name')->max('name', 120);
    if ($v->fails()) {
        flash('error', $v->first());
        redirect('/searches/' . (int)$params['id'] . '/edit');
    }
    SavedSearch::update((int)$search['id'], $data);
    flash('success', 'Search updated.');
    redirect('/dashboard');
});

$app->post('/searches/{id}/delete', function ($params) {
    $search = SavedSearch::forUserOr404((int)$params['id'], Auth::id());
    if ($search !== null) {
        SavedSearch::delete((int)$search['id']);
        flash('success', 'Search deleted.');
    }
    redirect('/dashboard');
});

$app->post('/searches/{id}/toggle', function ($params) {
    $search = SavedSearch::forUserOr404((int)$params['id'], Auth::id());
    if ($search !== null) {
        SavedSearch::toggleActive((int)$search['id']);
        flash('success', $search['active'] ? 'Search paused.' : 'Search activated.');
    }
    redirect('/dashboard');
});

/* Settings ----------------------------------------------------------- */

$app->get('/settings', function () {
    $user = User::find(Auth::id());
    $tgToken = Verification::telegramLinkToken(Auth::id());
    render('pages/settings', [
        'title' => t('nav.settings'),
        'user' => $user,
        'tg_link' => (string)config('telegram.bot_username') !== ''
            ? 'https://t.me/' . config('telegram.bot_username') . '?start=' . $tgToken : null,
        'notifications' => Notification::forUser(Auth::id(), 100),
    ]);
});

$app->post('/settings', function () {
    $user = User::find(Auth::id());
    $action = (string)($_POST['action'] ?? 'profile');

    if ($action === 'profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $tz = (string)($_POST['timezone'] ?? 'UTC');
        if ($name !== '' && mb_strlen($name) <= 100) {
            User::updateProfile((int)$user['id'], ['name' => $name]);
        }
        if (in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            User::updateProfile((int)$user['id'], ['timezone' => $tz]);
        }
        flash('success', 'Profile updated.');
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify($current, (string)$user['password_hash'])) {
            flash('error', 'Current password is wrong.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } else {
            User::updateProfile((int)$user['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
            flash('success', 'Password changed.');
        }
    } elseif ($action === 'email_toggle') {
        User::updateProfile((int)$user['id'], [
            'email_notifications' => !empty($_POST['email_notifications']) ? 1 : 0,
            'notify_digest' => ($_POST['notify_digest'] ?? '') === '1' ? 1 : 0,
        ]);
        flash('success', 'Notification preferences saved.');
    } elseif ($action === 'telegram_unlink') {
        User::unlinkTelegram((int)$user['id']);
        flash('success', 'Telegram unlinked.');
    } elseif ($action === 'resend_verification') {
        $token = bin2hex(random_bytes(24));
        Db::q("INSERT INTO email_tokens (user_id, token, purpose, expires_at)
               VALUES (?,?, 'verify_email', datetime('now', '+1 day'))", [$user['id'], $token]);
        Verification::sendEmail($user, $token);
        flash('success', 'Verification email sent.');
    }
    redirect('/settings');
});

$app->get('/settings/notifications', function () {
    render('partials/notifications_list', ['notifications' => Notification::forUser(Auth::id(), 100)]);
});

/* ------------------------------------------------------------------ *
 * Telegram webhook (secret path; no CSRF — protected by secret + raw body)
 * ------------------------------------------------------------------ */

$app->post('/telegram/webhook/{secret}', function ($params) {
    $secret = (string)$params['secret'];
    if ($secret !== (string)config('telegram.webhook_secret')) {
        http_response_code(403);
        exit('forbidden');
    }
    $update = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $message = $update['message'] ?? null;
    $tg = new \Tendari\Notifiers\TelegramNotifier();
    if ($message === null) {
        http_response_code(200);
        exit('ok');
    }
    $chatId = (string)($message['chat']['id'] ?? '');
    $text = trim((string)($message['text'] ?? ''));
    [$cmd, $arg] = array_pad(preg_split('/\s+/', $text, 2) ?: [], 2, '');

    if ($cmd === '/start' && $arg !== '') {
        $user = Verification::consumeTelegramToken($arg);
        if ($user !== null) {
            User::linkTelegram((int)$user['id'], $chatId);
            $tg->sendMessage($chatId, '✅ Linked to Tendari as ' . $user['email']);
        } else {
            $tg->sendMessage($chatId, '❌ Invalid or expired link token. Get a fresh one from Tendari settings.');
        }
    } elseif ($cmd === '/stop') {
        $user = Db::one('SELECT * FROM users WHERE telegram_chat_id = ?', [$chatId]);
        if ($user !== null) {
            User::unlinkTelegram((int)$user['id']);
            $tg->sendMessage($chatId, '👋 Unlinked from Tendari.');
        }
    } elseif ($cmd === '/status') {
        $user = Db::one('SELECT * FROM users WHERE telegram_chat_id = ?', [$chatId]);
        $tg->sendMessage($chatId, $user !== null
            ? '🟢 Linked as ' . $user['email']
            : '⚪ Not linked. Open Tendari settings to link your account.');
    } else {
        $tg->sendMessage($chatId, "Commands: /start <token> · /stop · /status");
    }
    http_response_code(200);
    exit('ok');
});

/* ------------------------------------------------------------------ *
 * Admin
 * ------------------------------------------------------------------ */

$app->get('/admin/sources', function () {
    render('pages/admin_sources', ['title' => 'Sources', 'sources' => Source::all()]);
}, 'admin');

/* Admin: endpoint health --------------------------------------------- */

$app->get('/admin/health', function () {
    render('pages/admin_health', [
        'title' => 'Source health',
        'sources' => Source::all(),
        'probes' => \Tendari\Services\HealthChecker::stored(),
        'checklist' => \Tendari\Services\HealthChecker::configChecklist(),
    ]);
}, 'admin');

$app->post('/admin/health/check', function () {
    $sourceId = (int)($_POST['source_id'] ?? 0);
    $sources = $sourceId > 0
        ? Db::all('SELECT * FROM sources WHERE id = ?', [$sourceId])
        : Source::all();
    $results = \Tendari\Services\HealthChecker::probeAll($sources);
    $bad = array_filter($results, fn($r) => in_array($r['verdict'], ['down', 'changed', 'error'], true));
    flash(count($bad) === 0 ? 'success' : 'error',
        count($results) . ' endpoint(s) checked — ' . count($bad) . ' problem(s).');
    redirect('/admin/health');
}, 'admin');

/* Admin: logs --------------------------------------------------------- */

$app->get('/admin/logs', function () {
    $files = glob(dirname(__DIR__) . '/storage/logs/*.log') ?: [];
    rsort($files);
    $list = [];
    foreach ($files as $f) {
        $list[] = [
            'name' => basename($f),
            'size' => filesize($f),
            'lines' => count(file($f) ?: []),
        ];
    }
    render('pages/admin_logs', ['title' => 'Logs', 'files' => $list]);
}, 'admin');

$app->get('/admin/logs/{file}', function ($params) {
    $name = basename((string)$params['file']);
    if (!preg_match('/^[A-Za-z0-9._-]+\.log$/', $name)) {
        flash('error', 'Invalid log file name.');
        redirect('/admin/logs');
    }
    $path = dirname(__DIR__) . '/storage/logs/' . $name;
    if (!is_file($path)) {
        flash('error', 'Log file not found.');
        redirect('/admin/logs');
    }
    $lines = (int)($_GET['lines'] ?? 200);
    $lines = max(20, min($lines, 5000));
    $content = array_slice(file($path) ?: [], -$lines);
    render('pages/admin_log_view', ['title' => 'Log ' . $name, 'name' => $name, 'content' => $content, 'lines' => $lines]);
}, 'admin');

$app->post('/admin/sources/{id}/toggle', function ($params) {
    $source = Db::one('SELECT * FROM sources WHERE id = ?', [(int)$params['id']]);
    if ($source !== null) {
        Source::setEnabled((int)$source['id'], !(int)$source['enabled']);
        flash('success', 'Source ' . ($source['enabled'] ? 'disabled' : 'enabled') . '.');
    }
    redirect('/admin/sources');
}, 'admin');

$app->post('/admin/run-fetch', function () {
    $sourceId = (int)($_POST['source_id'] ?? 0);
    $sources = $sourceId > 0 ? Db::all('SELECT * FROM sources WHERE id = ?', [$sourceId]) : Source::enabled();
    $summary = \Tendari\Connectors\FetchRunner::run($sources);
    foreach ($summary as $s) {
        flash(($s['status'] === 'error') ? 'error' : 'success',
            $s['code'] . ': ' . $s['status'] . ' (new: ' . $s['new'] . ', awards: ' . $s['awards'] . ')');
    }
    redirect('/admin/sources');
}, 'admin');

$app->get('/admin/users', function () {
    render('pages/admin_users', ['title' => 'Users', 'users' => User::all()]);
}, 'admin');

$app->post('/admin/users/{id}/toggle-active', function ($params) {
    $user = User::find((int)$params['id']);
    if ($user !== null) {
        User::setActive((int)$user['id'], !(int)$user['is_active']);
        flash('success', 'User updated.');
    }
    redirect('/admin/users');
}, 'admin');

/* ------------------------------------------------------------------ *
 * Boot + seed sources
 * ------------------------------------------------------------------ */

Db::migrate();
$app->dispatch();

/** Whitelist + normalize saved-search form input. */
function self_filter_search(array $post): array
{
    return [
        'name' => trim((string)($post['name'] ?? '')),
        'keywords' => trim((string)($post['keywords'] ?? '')),
        'exclude_keywords' => trim((string)($post['exclude_keywords'] ?? '')),
        'countries' => (array)($post['countries'] ?? []),
        'sectors' => (array)($post['sectors'] ?? []),
        'funding_types' => (array)($post['funding_types'] ?? []),
        'deadline_within_days' => $_POST['deadline_within_days'] ?? '',
        'active' => !empty($post['active']),
    ];
}
