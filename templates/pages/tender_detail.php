<?php /** @var array $tender @var array $aliases */ ?>
<section class="page-section">
  <div class="container wide">
<div class="page-head">
  <div>
    <a class="back-link" href="/tenders"><?= e(t('detail.back')) ?></a>
    <h1 class="title is-3 mt-2" style="max-width:46rem;"><?= e($tender['title']) ?></h1>
    <div class="tags mt-2">
      <span class="pill <?= $tender['status'] === 'open' ? 'is-open' : 'is-closed' ?>"><?= e($tender['status']) ?></span>
      <span class="pill is-neutral"><?= e($tender['funding_type'] ?? 'unknown') ?></span>
      <span class="pill is-neutral"><?= e($tender['source_name'] ?? '') ?></span>
      <?php foreach (json_list($tender['countries']) as $c): ?>
        <span class="pill is-neutral"><?= e($c) ?></span>
      <?php endforeach; ?>
      <?php foreach (json_list($tender['sectors']) as $s): ?>
        <span class="pill is-neutral"><?= e($s) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="columns is-variable is-5">
  <div class="column is-two-thirds">
    <div class="panel-card">
      <p class="panel-title"><?= e(t('detail.description')) ?></p>
      <div class="content">
        <?= nl2br(e($tender['description'] ?: t('detail.no_desc'))) ?>
      </div>
    </div>

    <div class="panel-card mt-4">
      <p class="panel-title"><?= e(t('detail.documents')) ?></p>
      <?php if (!empty($files)): ?>
        <ul class="file-list">
          <?php foreach ($files as $f): ?>
            <?php $ext = strtoupper(pathinfo((string)($f['filename'] ?? ''), PATHINFO_EXTENSION) ?: ($f['kind'] === 'page' ? 'HTML' : 'FILE')); ?>
            <li>
              <a href="/tenders/<?= (int)$tender['id'] ?>/files/<?= (int)$f['id'] ?>" target="_blank" rel="noopener">
                <span class="file-ext"><?= e($ext) ?></span>
                <span class="file-meta">
                  <span class="file-name"><?= e($f['label'] ?: ($f['filename'] ?? 'Document')) ?></span>
                  <span class="file-sub"><?= e($f['kind'] === 'page' ? t('detail.cached_page') : t('detail.tender_file')) ?> · <?= e(format_bytes($f['size'] !== null ? (int)$f['size'] : null)) ?></span>
                </span>
                <span class="file-dl"><?= e($f['kind'] === 'page' ? t('detail.view') : t('detail.download')) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="file-note"><?= e(t('detail.note')) ?></p>
      <?php else: ?>
        <p class="is-size-7" style="color:var(--muted);margin:0;"><?= e(t('detail.no_docs')) ?></p>
      <?php endif; ?>
    </div>
  </div>
  <div class="column">
    <div class="panel-card">
      <p class="panel-title"><?= e(t('detail.details')) ?></p>
      <table class="detail-meta">
        <tbody>
          <tr><th><?= e(t('detail.organization')) ?></th><td><?= e($tender['organization'] ?: '—') ?></td></tr>
          <tr><th><?= e(t('detail.published')) ?></th><td><?= e($tender['published_at'] ?: '—') ?></td></tr>
          <tr><th><?= e(t('detail.deadline')) ?></th><td><?= e($tender['deadline_at'] ?: '—') ?>
            <?php if (!empty($tender['deadline_at'])): ?><span style="color:var(--muted);font-size:.8rem;">(<?= e(time_diff($tender['deadline_at'])) ?>)</span><?php endif; ?>
          </td></tr>
          <tr><th><?= e(t('detail.value')) ?></th><td><?= $tender['value_amount'] !== null ? e(number_format((float)$tender['value_amount']) . ' ' . ($tender['value_currency'] ?? '')) : '—' ?></td></tr>
          <tr><th><?= e(t('detail.first_seen')) ?></th><td><?= e($tender['first_seen_at']) ?></td></tr>
        </tbody>
      </table>
      <div class="mt-4">
        <a class="button is-primary is-fullwidth" href="<?= e($tender['source_url']) ?>" target="_blank" rel="noopener"><?= e(t('detail.open_source')) ?></a>
        <a class="button is-light is-fullwidth mt-2" href="/searches?q=<?= e($tender['title']) ?>"><?= e(t('detail.save_search')) ?></a>
      </div>
    </div>
  </div>
</div>

<?php
$intel = $intelligence ?? ['bands' => [], 'similar' => [], 'sample' => 0, 'min_sample' => 5, 'basis' => 'none'];
$access = $intel_access ?? ['reason' => 'guest', 'plan' => 'guest', 'limit' => 0, 'used' => 0,
                            'remaining' => 0, 'unlimited' => false, 'period' => ''];
$bands = $intel['bands'];
$similar = $intel['similar'];
$basis = $intel['basis'];
$basisLabel = match ($basis) {
    'sector' => t('intelligence.basis_sector'),
    'country' => t('intelligence.basis_country'),
    default => t('intelligence.basis_all'),
};
$lockedMsg = match ($access['reason']) {
    'guest' => t('intelligence.locked_guest'),
    'exhausted' => t('intelligence.locked_quota', [$access['used'], $access['limit']]),
    default => t('intelligence.locked_plan'),
};
?>
<?php if ($intelligence === null): ?>
<div class="panel-card mt-4">
  <p class="panel-title"><?= e(t('intelligence.title')) ?></p>
  <p class="is-size-7" style="color:var(--muted);margin:.25rem 0 1rem;"><?= e(t('intelligence.sub')) ?></p>
  <div style="border:1px dashed var(--line);border-radius:10px;padding:1.25rem;text-align:center;">
    <p style="font-weight:700;margin-bottom:.35rem;"><?= e(t('intelligence.locked_title')) ?></p>
    <p class="is-size-7" style="color:var(--muted);margin:0 auto .9rem;max-width:34rem;"><?= e($lockedMsg) ?></p>
    <div class="buttons is-centered" style="margin-bottom:0;">
      <?php if ($access['reason'] === 'guest'): ?>
        <a class="button is-light" href="/login"><?= e(t('intelligence.cta_login')) ?></a>
      <?php endif; ?>
      <a class="button is-primary" href="/pricing"><?= e(t('intelligence.cta_upgrade')) ?></a>
    </div>
  </div>
</div>
<?php else: ?>
<div class="panel-card mt-4">
  <p class="panel-title"><?= e(t('intelligence.title')) ?></p>
  <p class="is-size-7" style="color:var(--muted);margin:.25rem 0 1rem;"><?= e(t('intelligence.sub')) ?></p>
  <?php if ($intel_has_data && $access['plan'] !== 'guest'): ?>
    <p class="is-size-7" style="color:var(--muted);margin:-.5rem 0 1rem;">
      <?= e($access['unlimited']
          ? t('intelligence.usage_unlimited')
          : t('intelligence.usage', [$access['used'], $access['limit']])) ?>
    </p>
  <?php endif; ?>
  <div class="columns is-variable is-5">
    <div class="column is-two-fifths">
      <h3 class="title is-6" style="margin-bottom:.35rem;"><?= e(t('intelligence.estimate')) ?></h3>
      <p class="is-size-7" style="color:var(--muted);margin-bottom:.75rem;"><?= e($basisLabel) ?></p>
      <?php if ($bands): ?>
        <?php foreach ($bands as $b): ?>
          <div style="border:1px solid var(--line);border-radius:10px;padding:.85rem;margin-bottom:.75rem;">
            <div class="is-flex is-justify-content-space-between is-align-items-center" style="gap:.5rem;">
              <strong><?= e($b['currency']) ?></strong>
              <span class="pill is-neutral"><?= e(t('intelligence.count', [$b['count']])) ?></span>
            </div>
            <p class="is-size-7" style="color:var(--muted);margin:.45rem 0 .15rem;"><?= e(t('intelligence.range')) ?></p>
            <p style="font-weight:700;margin:0;"><?= e(number_format($b['p25'])) ?> – <?= e(number_format($b['p75'])) ?> <?= e($b['currency']) ?></p>
            <table class="detail-meta" style="margin-top:.55rem;">
              <tbody>
                <tr><th><?= e(t('intelligence.low')) ?></th><td><?= e(number_format($b['min'])) ?></td></tr>
                <tr><th><?= e(t('intelligence.median')) ?></th><td><?= e(number_format($b['median'])) ?></td></tr>
                <tr><th><?= e(t('intelligence.high')) ?></th><td><?= e(number_format($b['max'])) ?></td></tr>
              </tbody>
            </table>
            <?php if ($b['your_percentile'] !== null): ?>
              <p class="is-size-7" style="color:var(--teal);font-weight:600;margin:.55rem 0 0;">
                <?= e(t('intelligence.position', [number_format((float)$b['your_value']), $b['your_percentile']])) ?>
              </p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="is-size-7" style="color:var(--muted);">
          <?= e(t('intelligence.collecting', [$intel['sample'], $intel['min_sample']])) ?>
        </p>
      <?php endif; ?>
      <p class="is-size-7" style="color:var(--muted);margin-top:.5rem;"><?= e(t('intelligence.disclaimer')) ?></p>
    </div>
    <div class="column">
      <h3 class="title is-6" style="margin-bottom:.35rem;"><?= e(t('intelligence.similar')) ?></h3>
      <?php if ($similar): ?>
        <div class="table-container">
          <table class="table is-fullwidth is-narrow is-size-7">
            <thead>
              <tr>
                <th><?= e(t('intelligence.award')) ?></th>
                <th><?= e(t('intelligence.winner')) ?></th>
                <th><?= e(t('intelligence.amount')) ?></th>
                <th><?= e(t('intelligence.awarded')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($similar as $a): ?>
                <tr>
                  <td>
                    <?= e($a['title']) ?>
                    <?php if (!empty($a['source_url'])): ?>
                      <a href="<?= e($a['source_url']) ?>" target="_blank" rel="noopener" style="color:var(--teal);font-weight:700;">↗</a>
                    <?php endif; ?>
                    <?php if (!empty($a['buyer'])): ?><br><span style="color:var(--muted);"><?= e($a['buyer']) ?></span><?php endif; ?>
                  </td>
                  <td><?= e($a['winner'] ?: '—') ?></td>
                  <td><?= $a['amount'] !== null ? e(number_format((float)$a['amount']) . ' ' . ($a['currency'] ?? '')) : '—' ?></td>
                  <td><?= e($a['awarded_at'] !== null ? substr((string)$a['awarded_at'], 0, 10) : '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="is-size-7" style="color:var(--muted);"><?= e(t('intelligence.none_yet')) ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($aliases) && count($aliases) > 1): ?>
  <div class="panel-card mt-4">
    <p class="panel-title"><?= e(t('detail.other_sources')) ?></p>
    <div class="tags">
      <?php foreach ($aliases as $a): ?>
        <span class="pill is-neutral"><?= e($a['source_name']) ?>
          <?php if (!empty($a['url'])): ?><a href="<?= e($a['url']) ?>" target="_blank" rel="noopener" style="color:var(--teal);font-weight:700;">↗</a><?php endif; ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

  </div>
</section>
