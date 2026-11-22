<?php
/** @var array $plans @var bool $billing_enabled @var array|null $subscription @var bool $canceled */
$csrf = \Tendari\Core\Auth::csrfToken();
$loggedIn = \Tendari\Core\Auth::check();
$sales = (string)config('billing.sales_email');
$salesLink = 'mailto:' . $sales . '?subject=' . rawurlencode('Tendari plan enquiry');
?>
<div class="pricing-table">
  <section class="page-section">
    <div class="container wide">
      <div class="section-head">
        <p class="kicker"><?= e(t('pricing.kicker')) ?></p>
        <h2 class="title"><?= e(t('pricing.title')) ?></h2>
        <p><?= e(t('pricing.sub')) ?></p>
      </div>

      <?php if ($canceled): ?>
        <div class="notification is-warning has-text-centered" style="border-radius:12px;"><?= e(t('pricing.canceled')) ?></div>
      <?php endif; ?>
      <?php if (!$billing_enabled): ?>
        <div class="notification is-info is-light has-text-centered" style="border-radius:12px;">
          <?= e(t('pricing.coming_soon')) ?> <a href="/#waitlist"><?= e(t('pricing.waitlist_cta')) ?></a>
        </div>
      <?php endif; ?>

      <div class="price-toggle" role="group" aria-label="Billing interval">
        <button type="button" data-price-interval="month" class="is-active" aria-pressed="true"><?= e(t('pricing.monthly')) ?></button>
        <button type="button" data-price-interval="year" aria-pressed="false"><?= e(t('pricing.yearly')) ?> <span class="save"><?= e(t('pricing.save')) ?></span></button>
      </div>

      <div class="price-grid">
        <?php foreach ($plans as $key => $plan): ?>
          <?php
            $isFree = (int)($plan['price_monthly'] ?? 0) === 0 && empty($plan['custom']);
            $isCustom = !empty($plan['custom']);
            $featured = !empty($plan['featured']);
            $active = $subscription !== null && $subscription['plan'] === $key
                && in_array($subscription['status'], ['active', 'trialing'], true);
            $canCheckout = \Tendari\Services\Billing::checkoutAvailable($key, 'month')
                || \Tendari\Services\Billing::checkoutAvailable($key, 'year');
            $monthly = (int)($plan['price_monthly'] ?? 0);
            $yearly = $plan['price_yearly'] ?? null;
          ?>
          <div class="price-card <?= $featured ? 'is-featured' : '' ?> <?= $isCustom || $key === 'business' ? 'is-dark-card' : '' ?>">
            <?php if ($featured): ?><span class="price-badge"><?= e(t('pricing.popular')) ?></span><?php endif; ?>
            <p class="plan-name"><?= e($plan['name']) ?></p>

            <?php if ($isFree): ?>
              <p class="plan-price"><?= e(t('free.plan')) ?></p>
              <p class="plan-bill"><?= e(t('pricing.forever')) ?></p>
            <?php elseif ($isCustom): ?>
              <p class="plan-price"><?= e(t('home.plan_custom')) ?></p>
              <p class="plan-bill"><?= e(t('pricing.custom_quote')) ?></p>
            <?php else: ?>
              <p class="plan-price">
                <span class="amt-monthly">$<?= $monthly ?><span class="per"><?= e(t('home.plan_permo')) ?></span></span>
                <span class="amt-yearly"><?= $yearly ? '$' . (int)$yearly . '<span class="per">' . e(t('pricing.per_year')) . '</span>' : e(t('home.plan_custom')) ?></span>
              </p>
              <p class="plan-bill">
                <span class="amt-monthly"><?= e($plan['price_note'] ?? t('pricing.billed')) ?></span>
                <span class="amt-yearly"><?= $yearly ? e(t('pricing.per_month', [round((int)$yearly / 12), max(0, (int)round(100 - ((int)$yearly / max(1, $monthly * 12) * 100)))])) : e(t('pricing.contact_sales_short')) ?></span>
              </p>
            <?php endif; ?>

            <?php if (!empty($plan['tagline'])): ?>
              <p class="plan-desc"><?= e($plan['tagline']) ?></p>
            <?php endif; ?>

            <ul class="plan-features">
              <?php foreach ($plan['features'] as $f): ?>
                <li><span class="tick">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span><?= e($f) ?></li>
              <?php endforeach; ?>
            </ul>

            <?php if ($active): ?>
              <span class="tag is-success is-medium"><?= e(t('pricing.your_plan')) ?></span>
            <?php elseif ($isFree): ?>
              <a class="button is-light is-fullwidth" href="<?= $loggedIn ? '/dashboard' : '/register' ?>">
                <?= $loggedIn ? e(t('pricing.included')) : e(t('pricing.start_free')) ?>
              </a>
            <?php elseif ($isCustom || $key === 'business'): ?>
              <a class="button <?= $isCustom ? 'is-light' : 'is-primary' ?> is-fullwidth" href="<?= e($salesLink) ?>"><?= e(t('pricing.contact_sales')) ?></a>
            <?php elseif ($canCheckout): ?>
              <?php if ($loggedIn): ?>
                <form method="post" action="/checkout">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="plan" value="<?= e($key) ?>">
                  <input type="hidden" name="interval" value="month">
                  <button class="button <?= $featured ? 'is-primary' : 'is-dark-btn' ?> is-fullwidth"><?= e(t('pricing.choose', [$plan['name']])) ?></button>
                </form>
              <?php else: ?>
                <a class="button <?= $featured ? 'is-primary' : 'is-dark-btn' ?> is-fullwidth" href="/register"><?= e(t('pricing.register_subscribe')) ?></a>
              <?php endif; ?>
            <?php else: ?>
              <a class="button is-light is-fullwidth" href="/#waitlist"><?= e(t('pricing.waitlist_cta')) ?></a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <p class="pricing-foot"><?= e(t('pricing.foot')) ?> <a href="<?= e($salesLink) ?>"><?= e(t('pricing.talk')) ?></a>.</p>
    </div>
  </section>
</div>

<section class="page-section" style="padding-top:0;">
  <div class="container" style="max-width:46rem;">
    <div class="section-head">
      <p class="kicker"><?= e(t('pricing.faq_kicker')) ?></p>
      <h2 class="title"><?= e(t('pricing.faq_title')) ?></h2>
    </div>
    <?php
      $faqs = [];
      for ($i = 1; $i <= 5; $i++) {
          $faqs[] = [t('faq.q' . $i), t('faq.a' . $i)];
      }
      foreach ($faqs as [$q, $a]): ?>
      <div class="feature-card mb-3">
        <p class="title mb-1" style="font-size:.95rem;"><?= e($q) ?></p>
        <p style="font-size:.88rem;"><?= e($a) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
