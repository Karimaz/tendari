<?php
/** @var array $recent @var array $stats @var int $waitlist_count @var bool $billing_enabled */
$csrf = \Tendari\Core\Auth::csrfToken();
$live = (int)$stats['tenders'] > 0;
?>
<section class="hero-landing">
  <div class="container wide">
    <div class="columns is-vcentered is-variable is-8">
      <div class="column is-6">
        <span class="eyebrow"><span class="pulse"></span> <?= e(t('home.eyebrow')) ?></span>
        <h1 class="title"><?= e(t('home.title1')) ?><br><?= e(t('home.title2')) ?> <span class="accent"><?= e(t('home.title3')) ?></span></h1>
        <p class="lede"><?= e(t('home.lede')) ?></p>
        <div class="buttons mt-4">
          <a class="button is-primary is-medium" href="/register"><?= e(t('home.cta_create')) ?></a>
          <a class="button is-ghost is-medium" href="/tenders"><?= e(t('home.cta_browse')) ?></a>
        </div>
        <p class="hero-note"><?= e(t('home.note')) ?></p>
      </div>
      <div class="column is-5 is-offset-1">
        <div class="preview-card">
          <div class="preview-head">
            <span class="k"><?= e(t('home.live')) ?></span>
            <div class="preview-stat">
              <span class="n"><?= $live ? number_format((int)$stats['tenders']) : '—' ?></span>
              <span class="l"><?= e(t('home.open_notices')) ?></span>
            </div>
          </div>
          <?php if ($live): foreach (array_slice($recent, 0, 3) as $t): ?>
            <div class="preview-row">
              <span class="t"><?= e(mb_strimwidth($t['title'], 0, 46, '…')) ?></span>
              <span class="m"><?= e($t['source_name'] ?? 'source') ?></span>
            </div>
          <?php endforeach; ?>
          <?php else: ?>
            <div class="preview-row"><span class="t"><?= e(t('home.fallback1')) ?></span></div>
            <div class="preview-row"><span class="t"><?= e(t('home.fallback2')) ?></span></div>
            <div class="preview-row"><span class="t"><?= e(t('home.fallback3')) ?></span></div>
          <?php endif; ?>
          <p class="preview-foot"><?= $live ? e(t('home.preview_live', [(int)$stats['sources'], (int)$stats['countries']])) : e(t('home.preview_new')) ?></p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-tight" style="padding-bottom:0;">
  <div class="container wide">
    <?php
      $logoDir = dirname(__DIR__, 2) . '/public/assets/sources/';
      $sources = [
        ['Etimad', 'Saudi Arabia', null, 'https://etimad.sa', 'E'],
        ['Saudi Aramco', 'Saudi Arabia', 'aramco.com.png', 'https://www.aramco.com/en/what-we-do/suppliers', 'A'],
        ['Abu Dhabi Gate', 'UAE', 'adgpg.gov.ae.png', 'https://supplier.adgpg.gov.ae', 'AD'],
        ['Dubai eSupply', 'UAE', 'esupply.dubai.gov.ae.png', 'https://esupply.dubai.gov.ae', 'D'],
        ['UAE Federal Register', 'UAE', 'mof.gov.ae.png', 'https://mof.gov.ae', 'UAE'],
        ['Monaqasat', 'Qatar', 'monaqasat.mof.gov.qa.png', 'https://monaqasat.mof.gov.qa', 'Q'],
        ['eTenders', 'South Africa', 'etenders.gov.za.png', 'https://www.etenders.gov.za', 'ZA'],
        ['PPIP', 'Kenya', 'tenders.go.ke.png', 'https://tenders.go.ke', 'KE'],
        ['NOCOPO', 'Nigeria', 'nocopo.bpp.gov.ng.png', 'https://nocopo.bpp.gov.ng', 'NG'],
        ['GHANEPS', 'Ghana', 'ghaneps.gov.gh.png', 'https://www.ghaneps.gov.gh', 'GH'],
        ['NeST', 'Tanzania', 'nest.go.tz.png', 'https://nest.go.tz', 'TZ'],
        ['Uganda GPP', 'Uganda', 'gpp.ppda.go.ug.png', 'https://gpp.ppda.go.ug', 'UG'],
        ['Umucyo', 'Rwanda', 'umucyo.gov.rw.png', 'https://www.umucyo.gov.rw', 'RW'],
        ['eGP', 'Ethiopia', 'egp.gov.et.png', 'https://egp.gov.et', 'ET'],
        ['eTenders', 'Egypt', null, 'https://etenders.gov.eg', 'EG'],
        ['Marchés Publics', 'Morocco', null, 'https://www.marchespublics.gov.ma', 'MA'],
        ['AfDB', 'Pan-African', 'afdb.org.png', 'https://www.afdb.org/en/projects-and-operations/procurement', 'AfDB'],
        ['World Bank', 'Global', 'worldbank.org.png', 'https://projects.worldbank.org/en/projects-operations/procurement', 'WB'],
      ];
    ?>
    <div class="coverage-head">
      <p class="label-k"><?= e(t('home.coverage_label')) ?></p>
      <p class="coverage-sub"><?= e(t('home.coverage_sub')) ?></p>
    </div>
    <div class="source-logos">
      <?php foreach ($sources as [$name, $region, $img, $href, $mono]): ?>
        <a class="source-logo" href="<?= e($href) ?>" target="_blank" rel="noopener" title="<?= e($name . ' — ' . $region) ?>">
          <?php if ($img !== null && is_file($logoDir . $img)): ?>
            <img src="/assets/sources/<?= e($img) ?>" alt="" loading="lazy" width="22" height="22">
          <?php else: ?>
            <span class="source-mono" aria-hidden="true"><?= e($mono) ?></span>
          <?php endif; ?>
          <span class="source-meta">
            <span class="source-name"><?= e($name) ?></span>
            <span class="source-region"><?= e($region) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="container wide">
    <div class="section-head">
      <p class="kicker"><?= e(t('home.why_kicker')) ?></p>
      <h2 class="title"><?= e(t('home.why_title')) ?></h2>
      <p><?= e(t('home.why_desc')) ?></p>
    </div>
    <div class="columns is-multiline is-variable is-5">
      <div class="column is-half">
        <div class="feature-card">
          <div class="feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="16" y2="12"/><line x1="4" y1="18" x2="10" y2="18"/></svg>
          </div>
          <p class="title"><?= e(t('home.f1_title')) ?></p>
          <p><?= e(t('home.f1_body')) ?></p>
        </div>
      </div>
      <div class="column is-half">
        <div class="feature-card">
          <div class="feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>
          </div>
          <p class="title"><?= e(t('home.f2_title')) ?></p>
          <p><?= e(t('home.f2_body')) ?></p>
        </div>
      </div>
      <div class="column is-half">
        <div class="feature-card">
          <div class="feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
          </div>
          <p class="title"><?= e(t('home.f3_title')) ?></p>
          <p><?= e(t('home.f3_body')) ?></p>
        </div>
      </div>
      <div class="column is-half">
        <div class="feature-card">
          <div class="feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
          <p class="title"><?= e(t('home.f4_title')) ?></p>
          <p><?= e(t('home.f4_body')) ?></p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-tight" style="padding-top:0;">
  <div class="container wide">
    <div class="section-head">
      <p class="kicker"><?= e(t('home.how_kicker')) ?></p>
      <h2 class="title"><?= e(t('home.how_title')) ?></h2>
    </div>
    <div class="columns is-variable is-4">
      <div class="column step">
        <div class="num">1</div>
        <p class="title"><?= e(t('home.step1_title')) ?></p>
        <p><?= e(t('home.step1_body')) ?></p>
      </div>
      <div class="column step">
        <div class="num">2</div>
        <p class="title"><?= e(t('home.step2_title')) ?></p>
        <p><?= e(t('home.step2_body')) ?></p>
      </div>
      <div class="column step">
        <div class="num">3</div>
        <p class="title"><?= e(t('home.step3_title')) ?></p>
        <p><?= e(t('home.step3_body')) ?></p>
      </div>
    </div>
  </div>
</section>

<?php if ($live): ?>
<section class="section-tight" style="padding-top:0;">
  <div class="container wide">
    <div class="stat-band">
      <div class="columns is-mobile has-text-centered is-variable is-4">
        <div class="column"><p class="n mb-1"><?= number_format((int)$stats['tenders']) ?></p><p class="l"><?= e(t('home.stats_open')) ?></p></div>
        <div class="column"><p class="n mb-1"><?= (int)$stats['sources'] ?></p><p class="l"><?= e(t('home.stats_sources')) ?></p></div>
        <div class="column"><p class="n mb-1"><?= (int)$stats['countries'] ?></p><p class="l"><?= e(t('home.stats_countries')) ?></p></div>
        <div class="column"><p class="n mb-1"><?= number_format((int)$waitlist_count) ?></p><p class="l"><?= e(t('home.stats_waitlist')) ?></p></div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section-tight" style="padding-top:0;">
  <div class="container wide">
    <div class="section-head">
      <p class="kicker"><?= e(t('home.pricing_kicker')) ?></p>
      <h2 class="title"><?= e(t('home.pricing_title')) ?></h2>
      <p><?= e(t('home.pricing_sub')) ?></p>
    </div>
    <div class="price-grid price-grid-mini">
      <?php $plans = \Tendari\Services\Billing::plans(); foreach ($plans as $key => $plan): ?>
        <?php
          $isFree = (int)($plan['price_monthly'] ?? 0) === 0 && empty($plan['custom']);
          $isCustom = !empty($plan['custom']);
        ?>
        <a class="price-mini <?= !empty($plan['featured']) ? 'is-featured' : '' ?>" href="/pricing">
          <span class="mini-name"><?= e($plan['name']) ?></span>
          <span class="mini-price">
            <?php if ($isFree): ?><?= e(t('free.plan')) ?>
            <?php elseif ($isCustom): ?><?= e(t('home.plan_custom')) ?>
            <?php else: ?>$<?= (int)$plan['price_monthly'] ?><small><?= e(t('home.plan_permo')) ?></small><?php endif; ?>
          </span>
          <span class="mini-cta"><?= e(t('home.plan_view')) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="pricing-foot" style="text-align:center;"><a class="button is-light" href="/pricing"><?= e(t('home.compare')) ?></a></p>
  </div>
</section>

<section class="section-tight" style="padding-top:0;">
  <div class="container wide" id="waitlist">
    <div class="cta-band">
      <div class="columns is-vcentered is-variable is-6">
        <div class="column is-5">
          <h2 class="title"><?= e(t('home.waitlist_title')) ?></h2>
          <p><?= e(t('home.waitlist_body')) ?></p>
        </div>
        <div class="column is-6 is-offset-1">
          <form method="post" action="/waitlist">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="field has-addons mb-0">
              <div class="control is-expanded">
                <input class="input" type="email" name="email" placeholder="<?= e(t('home.waitlist_placeholder')) ?>" value="<?= e(old('email')) ?>" required>
              </div>
              <div class="control">
                <button class="button is-primary"><?= e(t('home.waitlist_button')) ?></button>
              </div>
            </div>
            <p class="small-note"><?= e(t('home.waitlist_note', [number_format((int)$waitlist_count)])) ?></p>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
