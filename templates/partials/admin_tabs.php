<?php /** @var string $active */ ?>
<div class="admin-tabs" style="border-bottom:1px solid var(--line);margin-bottom:1.6rem;">
  <div class="is-flex is-flex-wrap-wrap" style="gap:.2rem;">
    <?php foreach (['sources' => 'Sources', 'health' => 'Health', 'logs' => 'Logs', 'users' => 'Users'] as $key => $label): ?>
      <a href="/admin/<?= $key ?>"
         style="padding:.55rem 1rem;border-radius:10px 10px 0 0;font-size:.85rem;font-weight:600;text-decoration:none;<?= $active === $key ? 'color:var(--ink);background:#fff;border:1px solid var(--line);border-bottom-color:#fff;margin-bottom:-1px;' : 'color:var(--muted);' ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
