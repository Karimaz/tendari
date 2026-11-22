<?php
/** @var array $results @var array $filters @var int $limit @var ?int $cursor */
?>
<?php if (empty($results)): ?>
  <div class="panel-card has-text-centered">
    <p class="title is-5 mb-1" style="font-size:1rem;color:var(--ink);"><?= e(t('results.empty_title')) ?></p>
    <p class="is-size-7" style="color:var(--muted);"><?= e(t('results.empty_body')) ?></p>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table is-fullwidth is-hoverable">
      <thead>
        <tr>
          <th><?= e(t('results.title_col')) ?></th><th><?= e(t('results.org_col')) ?></th><th><?= e(t('results.source_col')) ?></th><th><?= e(t('results.countries_col')) ?></th><th><?= e(t('results.type_col')) ?></th><th><?= e(t('results.deadline_col')) ?></th>
        </tr>
      </thead>
      <tbody id="results-tbody">
        <?php render_partial('partials/tender_rows', ['results' => $results]); ?>
      </tbody>
    </table>
  </div>

  <?php if (count($results) >= $limit && $limit < 100): ?>
    <?php
      $next = $filters;
      $next['limit'] = $limit + 20;
      unset($next['partial'], $next['cursor'], $next['rows_only']);
      $nextUrl = '/tenders?' . http_build_query($next);
    ?>
    <div class="has-text-centered mt-5">
      <a class="button is-light load-more" href="<?= e($nextUrl) ?>"
         hx-get="<?= e($nextUrl) ?>" hx-target="#results" hx-swap="innerHTML"
         hx-push-url="false" hx-disabled-elt="this">
        <?= e(t('results.load_more')) ?>
      </a>
    </div>
  <?php endif; ?>
<?php endif; ?>
