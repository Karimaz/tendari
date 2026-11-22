<?php
/** @var string $title @var string $content */
$user = \Tendari\Core\Auth::user();
$flash = take_flash();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Tendari</title>
<meta name="csrf" content="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
<meta name="description" content="Tendari aggregates public tenders and funding calls across Africa and the Middle East into one searchable feed with email and Telegram alerts.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/vendor/bulma.min.css">
<link rel="stylesheet" href="/assets/app.css">
<script src="/assets/vendor/htmx.min.js"></script>
<script src="/assets/app.js" defer></script>
</head>
<body>
<nav class="navbar" role="navigation" aria-label="main navigation">
  <div class="container wide">
    <div class="navbar-brand">
      <a class="navbar-item brandmark" href="/">
        <span class="logo-badge"></span>
        <span class="word">tendari<span class="dot">.</span></span>
      </a>
      <a role="button" class="navbar-burger" data-target="nav-menu" aria-label="menu" aria-expanded="false">
        <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
      </a>
    </div>
    <div id="nav-menu" class="navbar-menu">
      <div class="navbar-start">
        <a class="navbar-item <?= $path === '/tenders' ? 'is-active' : '' ?>" href="/tenders">Tenders</a>
        <a class="navbar-item <?= $path === '/pricing' ? 'is-active' : '' ?>" href="/pricing">Pricing</a>
        <?php if ($user): ?>
          <a class="navbar-item <?= $path === '/dashboard' ? 'is-active' : '' ?>" href="/dashboard">Dashboard</a>
          <a class="navbar-item <?= $path === '/settings' ? 'is-active' : '' ?>" href="/settings">Settings</a>
        <?php endif; ?>
        <?php if ($user && (int)$user['is_admin'] === 1): ?>
          <a class="navbar-item" href="/admin/sources">Admin</a>
        <?php endif; ?>
      </div>
      <div class="navbar-end">
        <?php if ($user): ?>
          <div class="navbar-item user-chip is-hidden-mobile"><?= e($user['name'] ?: $user['email']) ?></div>
          <div class="navbar-item">
            <form method="post" action="/logout">
              <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
              <button class="button is-light">Log out</button>
            </form>
          </div>
        <?php else: ?>
          <a class="navbar-item" href="/login">Log in</a>
          <div class="navbar-item">
            <a class="button is-primary" href="/register">Get started</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<div id="toasts"></div>

<?php foreach ($flash as $f): ?>
  <div data-flash="<?= e($f['message']) ?>" data-flash-type="is-<?= $f['type'] === 'error' ? 'danger' : 'success' ?>" hidden></div>
<?php endforeach; ?>

<main><?= $content ?></main>

<footer class="site-footer">
  <div class="container wide">
    <div class="columns">
      <div class="column is-5">
        <div class="foot-brand brandmark mb-3">
          <span class="logo-badge">T</span>
          <span class="word">tendari<span class="dot">.</span></span>
        </div>
        <p class="is-size-7" style="max-width:22rem;color:#8aa0b5;">Tender notices &amp; funding opportunities across Africa &amp; the Middle East — one feed, your filters, instant alerts.</p>
      </div>
      <div class="column">
        <h4>Product</h4>
        <ul class="is-size-7" style="list-style:none;margin:0;">
          <li class="mb-2"><a href="/tenders">Browse tenders</a></li>
          <li class="mb-2"><a href="/pricing">Pricing</a></li>
          <li class="mb-2"><a href="/register">Create account</a></li>
        </ul>
      </div>
      <div class="column">
        <h4>Account</h4>
        <ul class="is-size-7" style="list-style:none;margin:0;">
          <li class="mb-2"><a href="/login">Log in</a></li>
          <li class="mb-2"><a href="/#waitlist">Waiting list</a></li>
        </ul>
      </div>
      <div class="column">
        <h4>Get in touch</h4>
        <ul class="is-size-7" style="list-style:none;margin:0;">
          <li class="mb-2"><a href="mailto:hello@tendari.org">hello@tendari.org</a></li>
        </ul>
      </div>
    </div>
    <div class="foot-bottom">
      © <?= date('Y') ?> tendari.org — data from public sources; always verify with the issuing organisation.
    </div>
  </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.navbar-burger').forEach(function (burger) {
    burger.addEventListener('click', function () {
      var target = document.getElementById(burger.dataset.target);
      burger.classList.toggle('is-active');
      target.classList.toggle('is-active');
    });
  });
  var nav = document.querySelector('.navbar');
  var onScroll = function () { nav.classList.toggle('is-scrolled', window.scrollY > 8); };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
});
</script>
</body>
</html>
