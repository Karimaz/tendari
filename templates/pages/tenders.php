<section class="page-section">
  <div class="container wide">
    <?php /** @var array $results @var array $filters @var int $limit @var ?int $cursor */ ?>
    <div class="page-head">
      <div>
        <h1 class="title is-3"><?= e(t('tenders.title')) ?></h1>
        <p class="sub"><?= e(t('tenders.sub')) ?></p>
      </div>
      <button type="button" class="button is-light is-small mobile-only" onclick="document.getElementById('filter-bar').classList.toggle('is-open')"><?= e(t('tenders.filters_btn')) ?></button>
    </div>

    <?php render_partial('partials/filter_bar', ['filters' => $filters]); ?>

    <div id="results">
      <?php render_partial('partials/tender_results', ['results' => $results, 'filters' => $filters, 'limit' => $limit, 'cursor' => $cursor]); ?>
    </div>
  </div>
</section>
