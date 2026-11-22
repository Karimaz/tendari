<?php /** @var array $sources */
render_partial('partials/admin_tabs', ['active' => 'sources']); ?>
<section class="page-section">
  <div class="container wide">
<div class="page-head"><div>
  <h1 class="title is-3">Sources</h1>
  <p class="sub">Enable sources to let the fetch cron pull them. Status comes from the last fetch run.</p>
</div></div>

<table class="table is-fullwidth is-size-7">
  <thead>
    <tr><th>Code</th><th>Name</th><th>Region</th><th>Interval</th><th>Last fetched</th><th>Status</th><th>Enabled</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($sources as $s): ?>
      <tr>
        <td><code><?= e($s['code']) ?></code></td>
        <td><?= e($s['name']) ?></td>
        <td><?= e($s['region'] ?? '') ?></td>
        <td><?= (int)$s['fetch_interval_min'] ?>m</td>
        <td><?= e($s['last_fetched_at'] ?? 'never') ?></td>
        <td>
          <?php if ($s['last_status'] === 'error'): ?>
            <span class="tag is-danger" title="<?= e($s['last_error'] ?? '') ?>">error</span>
            <span class="is-size-7 has-text-danger"><?= e(mb_substr((string)($s['last_error'] ?? ''), 0, 60)) ?></span>
          <?php elseif ($s['last_status'] === 'empty'): ?>
            <span class="tag is-warning">empty</span>
          <?php elseif ($s['last_status'] === 'ok'): ?>
            <span class="tag is-success">ok</span>
          <?php else: ?>
            <span class="tag is-light">—</span>
          <?php endif; ?>
        </td>
        <td><?= $s['enabled'] ? '<span class="tag is-success">on</span>' : '<span class="tag is-light">off</span>' ?></td>
        <td>
          <form method="post" action="/admin/sources/<?= (int)$s['id'] ?>/toggle" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
            <button class="button is-small <?= $s['enabled'] ? 'is-warning' : 'is-success' ?>"><?= $s['enabled'] ? 'Disable' : 'Enable' ?></button>
          </form>
          <form method="post" action="/admin/run-fetch" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="source_id" value="<?= (int)$s['id'] ?>">
            <button class="button is-small is-link">Run now</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<form method="post" action="/admin/run-fetch" class="mt-4">
  <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
  <button class="button is-primary">Run all enabled sources now</button>
</form>

  </div>
</section>
