<?php
/** @var array $files */
render_partial('partials/admin_tabs', ['active' => 'logs']);
?>
<section class="page-section">
  <div class="container wide">
<div class="page-head"><div>
  <h1 class="title is-3">Daily logs</h1>
  <p class="sub">One file per day (UTC) in <code>storage/logs/</code>.</p>
</div></div>

<?php if (empty($files)): ?>
  <div class="notification is-light">No log files yet. Logs are written by crons and connector runs.</div>
<?php else: ?>
  <table class="table is-fullwidth is-size-7 is-hoverable">
    <thead><tr><th>File</th><th>Lines</th><th>Size</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($files as $f): ?>
        <tr>
          <td><a href="/admin/logs/<?= e($f['name']) ?>"><code><?= e($f['name']) ?></code></a></td>
          <td><?= (int)$f['lines'] ?></td>
          <td><?= number_format((float)$f['size'] / 1024, 1) ?> KB</td>
          <td>
            <a class="button is-small" href="/admin/logs/<?= e($f['name']) ?>?lines=5000">Full</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

  </div>
</section>
