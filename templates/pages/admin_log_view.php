<?php
/** @var string $name @var array $content @var int $lines */
render_partial('partials/admin_tabs', ['active' => 'logs']);
?>
<section class="page-section">
  <div class="container wide">
<nav class="level">
  <h1 class="title is-4"><code><?= e($name) ?></code></h1>
  <div>
    <a class="button is-small" href="/admin/logs">← All logs</a>
    <a class="button is-small" href="/admin/logs/<?= e($name) ?>?lines=<?= min($lines * 5, 5000) ?>">More lines (<?= min($lines * 5, 5000) ?>)</a>
  </div>
</nav>
<div class="box" style="max-height:70vh;overflow:auto;">
  <pre style="white-space:pre-wrap;word-break:break-word;font-size:12px;"><?=
    e(implode('', $content)) !== '' ? e(implode('', $content)) : '(empty log file)'
  ?></pre>
</div>
<p class="is-size-7 has-text-grey">Showing last <?= count($content) ?> line(s).</p>

  </div>
</section>
