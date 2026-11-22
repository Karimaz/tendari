<?php
/** @var array $filters */
require dirname(__DIR__, 1) . '/options.php';
$countriesSel = json_list($filters['countries'] ?? []);
$sectorsSel = json_list($filters['sectors'] ?? []);
$activeCount = 0;
foreach (['q', 'funding_type', 'status', 'within_days', 'deadline_from', 'deadline_to'] as $k) {
    if (trim((string)($filters[$k] ?? '')) !== '') $activeCount++;
}
$activeCount += count($countriesSel) + count($sectorsSel);
?>
<form id="filter-bar" class="filter-bar" method="get" action="/tenders"
      hx-get="/tenders" hx-target="#results" hx-swap="innerHTML" hx-push-url="true">
  <div class="filter-grid">
    <div class="filter-field">
      <label class="label"><?= e(t('filter.keywords')) ?></label>
      <input class="input" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="water supply contract">
    </div>
    <div class="filter-field">
      <label class="label"><?= e(t('filter.countries')) ?> <span class="filter-hint"><?= e(t('filter.pick_many')) ?></span></label>
      <div class="select is-multiple">
        <select name="countries[]" multiple size="4">
          <?php foreach (th_country_options() as $c2 => $label): ?>
            <option value="<?= e($c2) ?>" <?= in_array($c2, $countriesSel, true) ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="filter-field">
      <label class="label"><?= e(t('filter.sectors')) ?> <span class="filter-hint"><?= e(t('filter.pick_many')) ?></span></label>
      <div class="select is-multiple">
        <select name="sectors[]" multiple size="4">
          <?php foreach (th_sector_options() as $sector): ?>
            <option value="<?= e($sector) ?>" <?= in_array($sector, $sectorsSel, true) ? 'selected' : '' ?>><?= e($sector) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="filter-field">
      <label class="label"><?= e(t('filter.funding_type')) ?></label>
      <div class="select">
        <select name="funding_type">
          <option value=""><?= e(t('filter.any')) ?></option>
          <?php foreach (['grant', 'procurement', 'loan', 'framework', 'unknown'] as $ft): ?>
            <option value="<?= e($ft) ?>" <?= ($filters['funding_type'] ?? '') === $ft ? 'selected' : '' ?>><?= e(ucfirst($ft)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <label class="label mt-3"><?= e(t('filter.status')) ?></label>
      <div class="select">
        <select name="status">
          <option value=""><?= e(t('filter.open_closed')) ?></option>
          <?php foreach (['open', 'closed'] as $st): ?>
            <option value="<?= e($st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(t('filter.' . $st)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="filter-field">
      <label class="label"><?= e(t('filter.within')) ?></label>
      <div class="select">
        <select name="within_days">
          <option value=""><?= e(t('filter.any_time')) ?></option>
          <?php foreach ([7, 14, 30, 60] as $d): ?>
            <option value="<?= $d ?>" <?= (int)($filters['within_days'] ?? 0) === $d ? 'selected' : '' ?>><?= e(t('filter.days', [$d])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="filter-field">
      <label class="label"><?= e(t('filter.from')) ?></label>
      <input class="input" type="date" name="deadline_from" value="<?= e($filters['deadline_from'] ?? '') ?>">
      <label class="label mt-3"><?= e(t('filter.to')) ?></label>
      <input class="input" type="date" name="deadline_to" value="<?= e($filters['deadline_to'] ?? '') ?>">
    </div>
  </div>
  <div class="filter-actions">
    <button type="submit" class="button is-primary"><?= e(t('filter.apply')) ?><?= $activeCount > 0 ? ' (' . $activeCount . ')' : '' ?></button>
    <button type="button" class="button is-light"
            onclick="this.form.reset(); if (this.form.requestSubmit) { this.form.requestSubmit(); } else { this.form.submit(); }"><?= e(t('filter.clear')) ?></button>
    <span class="filter-busy htmx-indicator"><?= e(t('filter.searching')) ?></span>
  </div>
</form>
