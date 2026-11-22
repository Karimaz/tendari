<?php
/** @var array $sources @var ?array $probes @var array $checklist */
render_partial('partials/admin_tabs', ['active' => 'health']);
$probeResults = $probes['results'] ?? [];
$verdictClass = [
    'ok' => 'is-success',
    'down' => 'is-danger',
    'changed' => 'is-warning',
    'stub' => 'is-light',
    'needs-credentials' => 'is-info',
    'error' => 'is-danger',
];
?>
<section class="page-section">
  <div class="container wide">

<?php if ($probes === null): ?>
  <div class="notification is-info is-light">
    No health checks have run yet. Click <strong>Check all now</strong> or schedule
    <code>php cron/health.php</code> regularly.
  </div>
<?php else: ?>
  <div class="notification is-light is-size-7">
    Last check: <strong><?= e($probes['checked_at']) ?></strong> (UTC)
  </div>
<?php endif; ?>

<div class="level">
  <form method="post" action="/admin/health/check">
    <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
    <button class="button is-primary">Check all endpoints now</button>
  </form>
  <a class="button is-light" href="/admin/sources">← Sources</a>
</div>

<h2 class="subtitle is-5 mt-4">Endpoint health</h2>
<table class="table is-fullwidth is-size-7">
  <thead>
    <tr><th>Source</th><th>Enabled</th><th>Last fetch</th><th>Last fetch status</th><th>Live probe</th><th>Probe detail</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($sources as $s): $p = $probeResults[$s['code']] ?? null; ?>
      <tr>
        <td><code><?= e($s['code']) ?></code></td>
        <td><?= $s['enabled'] ? '<span class="tag is-success is-light">on</span>' : '<span class="tag is-light">off</span>' ?></td>
        <td><?= e($s['last_fetched_at'] ?? 'never') ?></td>
        <td>
          <?php if ($s['last_status'] === 'ok'): ?><span class="tag is-success">ok</span>
          <?php elseif ($s['last_status'] === 'empty'): ?><span class="tag is-warning">empty</span>
          <?php elseif ($s['last_status'] === 'error'): ?>
            <span class="tag is-danger">error</span>
            <span class="has-text-danger"><?= e(mb_substr((string)($s['last_error'] ?? ''), 0, 60)) ?></span>
          <?php else: ?><span class="tag is-light">—</span><?php endif; ?>
        </td>
        <td>
          <?php if ($p === null): ?><span class="tag is-light">not probed</span>
          <?php else: ?>
            <span class="tag <?= $verdictClass[$p['verdict']] ?? 'is-light' ?>"><?= e($p['verdict']) ?></span>
            <?php if ($p['http_code'] !== null): ?>HTTP <?= (int)$p['http_code'] ?><?php endif; ?>
            <?php if ($p['latency_ms'] !== null): ?> · <?= (int)$p['latency_ms'] ?>ms<?php endif; ?>
          <?php endif; ?>
        </td>
        <td class="is-size-7" title="<?= e($p['url'] ?? '') ?>"><?= e($p['message'] ?? '—') ?></td>
        <td>
          <form method="post" action="/admin/health/check">
            <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="source_id" value="<?= (int)$s['id'] ?>">
            <button class="button is-small is-link" <?= $p === null || $p['url'] === null ? 'title="No probe URL (stub connector)" disabled' : '' ?>>Probe</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<p class="is-size-7 has-text-grey">
  Verdicts: <span class="tag is-success is-small">ok</span> live ·
  <span class="tag is-danger is-small">down</span> unreachable / HTTP error ·
  <span class="tag is-warning is-small">changed</span> responds but unexpected body (API moved or changed) ·
  <span class="tag is-light is-small">stub</span> scraper pending ·
  <span class="tag is-info is-small">needs-credentials</span> registration required.
</p>

<h2 class="subtitle is-5 mt-5">Go-live checklist</h2>
<div class="box">
  <table class="table is-fullwidth is-size-7 is-narrow">
    <tbody>
      <?php foreach ($checklist as $item): ?>
        <tr>
          <td><?= $item['ok'] ? '✅' : '❌' ?></td>
          <td><?= e($item['label']) ?></td>
          <td class="<?= $item['ok'] ? 'has-text-success' : 'has-text-danger' ?>"><?= $item['ok'] ? 'ready' : 'missing' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="is-size-7 has-text-grey">Schedule the health cron to keep this page fresh:</p>
<pre><code>*/30 * * * * php /path/to/cron/health.php   # exits 1 if any endpoint is down/changed</code></pre>

  </div>
</section>
