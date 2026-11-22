<?php /** @var array $searches @var array $recent_matches */ ?>
<section class="page-section">
  <div class="container wide">
<div class="page-head">
  <div>
    <h1 class="title is-3"><?= e(t('dashboard.title')) ?></h1>
    <p class="sub"><?= e(t('dashboard.sub')) ?></p>
  </div>
  <div class="buttons mb-0">
    <a class="button is-primary" href="/searches">+ <?= e(t('dashboard.new_search')) ?></a>
    <a class="button is-light" href="/tenders"><?= e(t('dashboard.browse')) ?></a>
  </div>
</div>

<h2 class="panel-title" style="margin-bottom:1rem;"><?= e(t('dashboard.saved')) ?></h2>
<?php if (empty($searches)): ?>
  <div class="panel-card has-text-centered">
    <p class="title is-5 mb-1" style="font-size:1.05rem;color:var(--ink);"><?= e(t('dashboard.no_searches_title')) ?></p>
    <p class="is-size-7 mb-3" style="color:var(--muted);"><?= e(t('dashboard.no_searches_body')) ?></p>
    <a class="button is-primary" href="/searches"><?= e(t('dashboard.create_first')) ?></a>
  </div>
<?php else: ?>
  <div class="columns is-multiline is-variable is-4">
    <?php foreach ($searches as $s): ?>
      <div class="column is-one-third">
        <div class="search-card">
          <div class="card-content">
            <p class="title is-6 mb-1">
              <?= e($s['name']) ?>
              <?php if ((int)$s['match_count'] > 0): ?>
                <span class="tag is-primary match-badge"><?= e(t('dashboard.matches', [(int)$s['match_count']])) ?></span>
              <?php endif; ?>
            </p>
            <p class="subtitle is-7 mb-0">
              <?php if ($s['keywords'] !== ''): ?><strong><?= e(t('dashboard.keywords')) ?>:</strong> <?= e($s['keywords']) ?><br><?php endif; ?>
              <?php if ($s['exclude_keywords'] !== ''): ?><strong><?= e(t('dashboard.excluding')) ?>:</strong> <?= e($s['exclude_keywords']) ?><br><?php endif; ?>
              <?php if (json_list($s['countries']) !== []): ?><strong><?= e(t('dashboard.countries')) ?>:</strong> <?= e(implode(', ', json_list($s['countries']))) ?><br><?php endif; ?>
              <?php if (json_list($s['funding_types']) !== []): ?><strong><?= e(t('dashboard.types')) ?>:</strong> <?= e(implode(', ', json_list($s['funding_types']))) ?><br><?php endif; ?>
              <?php if ($s['deadline_within_days'] !== null): ?><strong><?= e(t('dashboard.deadline')) ?>:</strong> <?= e(t('filter.days', [(int)$s['deadline_within_days']])) ?><br><?php endif; ?>
            </p>
            <div class="tags mb-0 mt-2">
              <span class="pill <?= $s['active'] ? 'is-open' : 'is-closed' ?>"><?= e($s['active'] ? t('dashboard.active') : t('dashboard.paused')) ?></span>
            </div>
          </div>
          <div class="card-footer">
            <a class="card-footer-item is-size-7" href="/tenders?q=<?= e($s['keywords']) ?>&amp;countries=<?= e(implode(',', json_list($s['countries']))) ?>&amp;funding_type=<?= e(json_list($s['funding_types'])[0] ?? '') ?>"><?= e(t('dashboard.test_run')) ?></a>
            <a class="card-footer-item is-size-7" href="/searches/<?= (int)$s['id'] ?>/edit"><?= e(t('dashboard.edit')) ?></a>
            <form method="post" action="/searches/<?= (int)$s['id'] ?>/toggle">
              <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
              <button class="card-footer-item is-size-7"><?= e($s['active'] ? t('dashboard.pause') : t('dashboard.activate')) ?></button>
            </form>
            <form method="post" action="/searches/<?= (int)$s['id'] ?>/delete">
              <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
              <button class="card-footer-item is-size-7 has-text-danger" data-confirm="<?= e(t('dashboard.delete_confirm')) ?>"><?= e(t('dashboard.delete')) ?></button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2 class="panel-title mt-5" style="margin-bottom:1rem;"><?= e(t('dashboard.recent')) ?></h2>
<?php render_partial('partials/notifications_list', ['notifications' => $recent_matches]); ?>

  </div>
</section>
