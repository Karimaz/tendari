<?php /** @var ?array $search @var array $prefill */
require dirname(__DIR__) . '/options.php'; ?>
<section class="page-section">
  <div class="container wide">
<div class="page-head">
  <div>
    <a class="back-link" href="/dashboard">← Dashboard</a>
    <h1 class="title is-3 mt-2"><?= e($search !== null ? t('sf.title_edit') : t('sf.title_new')) ?></h1>
    <p class="sub"><?= e(t('sf.sub')) ?></p>
  </div>
</div>

<?php
$vals = $search !== null ? $search : [
    'name' => $prefill['name'] ?? '',
    'keywords' => $prefill['keywords'] ?? $prefill['q'] ?? '',
    'exclude_keywords' => $prefill['exclude_keywords'] ?? '',
    'countries' => json_encode(json_list($prefill['countries'] ?? [])),
    'sectors' => '[]',
    'funding_types' => '[]',
    'deadline_within_days' => null,
    'active' => 1,
];
$action = $search !== null ? '/searches/' . (int)$search['id'] . '/edit' : '/searches';
$countriesSel = json_list($vals['countries']);
$sectorsSel = json_list($vals['sectors']);
$typesSel = json_list($vals['funding_types']);
?>

<form method="post" action="<?= e($action) ?>">
  <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
  <div class="panel-card">
    <div class="field">
      <label class="label"><?= e(t('sf.name')) ?></label>
      <input class="input" name="name" value="<?= e($vals['name']) ?>" required maxlength="120"
             placeholder="<?= e(t('sf.name_placeholder')) ?>">
    </div>
    <div class="field">
      <label class="label"><?= e(t('sf.keywords')) ?></label>
      <input class="input" name="keywords" value="<?= e($vals['keywords']) ?>" placeholder="<?= e(t('sf.keywords_placeholder')) ?>">
    </div>
    <div class="field">
      <label class="label"><?= e(t('sf.exclude')) ?></label>
      <input class="input" name="exclude_keywords" value="<?= e($vals['exclude_keywords']) ?>" placeholder="<?= e(t('sf.exclude_placeholder')) ?>">
    </div>
    <div class="columns">
      <div class="column">
        <label class="label"><?= e(t('sf.countries')) ?></label>
        <div class="select is-multiple">
          <select name="countries[]" multiple size="6">
            <?php foreach (th_country_options() as $c2 => $label): ?>
              <option value="<?= e($c2) ?>" <?= in_array($c2, $countriesSel, true) ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="column">
        <label class="label"><?= e(t('sf.sectors')) ?></label>
        <div class="select is-multiple">
          <select name="sectors[]" multiple size="6">
            <?php foreach (th_sector_options() as $sector): ?>
              <option value="<?= e($sector) ?>" <?= in_array($sector, $sectorsSel, true) ? 'selected' : '' ?>><?= e($sector) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="column">
        <label class="label"><?= e(t('sf.types')) ?></label>
        <div class="select is-multiple">
          <select name="funding_types[]" multiple size="6">
            <?php foreach (['grant', 'procurement', 'loan', 'framework'] as $ft): ?>
              <option value="<?= e($ft) ?>" <?= in_array($ft, $typesSel, true) ? 'selected' : '' ?>><?= e(ucfirst($ft)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="label mt-3"><?= e(t('sf.deadline_within')) ?></label>
        <input class="input" type="number" min="1" name="deadline_within_days"
               value="<?= e($vals['deadline_within_days'] ?? '') ?>">
        <label class="checkbox mt-3">
          <input type="checkbox" name="active" value="1" <?= !empty($vals['active']) ? 'checked' : '' ?>> <?= e(t('sf.active')) ?>
        </label>
      </div>
    </div>
  </div>
  <button class="button is-primary"><?= e($search !== null ? t('sf.save_changes') : t('sf.create')) ?></button>
  <a class="button is-light" href="/dashboard"><?= e(t('sf.cancel')) ?></a>
</form>

  </div>
</section>
