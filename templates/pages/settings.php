<?php /** @var array $user @var ?string $tg_link @var array $notifications */ ?>
<section class="page-section">
  <div class="container wide">
<div class="page-head">
  <div>
    <h1 class="title is-3"><?= e(t('settings.title')) ?></h1>
    <p class="sub"><?= e(t('settings.sub')) ?></p>
  </div>
</div>

<div class="columns is-multiline is-variable is-5">
  <div class="column is-half">
    <div class="panel-card settings-panel">
      <p class="panel-title"><?= e(t('settings.profile')) ?></p>
      <form method="post" action="/settings">
        <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="profile">
        <div class="field"><label class="label"><?= e(t('settings.name')) ?></label>
          <input class="input" name="name" value="<?= e($user['name']) ?>" maxlength="100">
        </div>
        <div class="field"><label class="label"><?= e(t('settings.email')) ?></label>
          <input class="input" value="<?= e($user['email']) ?>" disabled>
          <?php if ((int)$user['email_verified'] === 0): ?>
            <p class="help has-text-danger"><?= e(t('settings.not_verified')) ?>
              <button class="button is-small is-text" name="action" value="resend_verification" formaction="/settings"><?= e(t('settings.resend')) ?></button>
            </p>
          <?php else: ?><p class="help" style="color:#0b6b4f;"><?= e(t('settings.verified')) ?></p><?php endif; ?>
        </div>
        <div class="field"><label class="label"><?= e(t('settings.timezone')) ?></label>
          <?php $zones = \DateTimeZone::listIdentifiers(); $currentTz = (string)$user['timezone']; ?>
          <div class="select is-fullwidth">
            <select name="timezone">
              <?php if ($currentTz !== '' && !in_array($currentTz, $zones, true)): ?>
                <option value="<?= e($currentTz) ?>" selected><?= e($currentTz) ?> (current)</option>
              <?php endif; ?>
              <?php foreach ($zones as $tz): ?>
                <option value="<?= e($tz) ?>" <?= $tz === $currentTz ? 'selected' : '' ?>>
                  <?= e(str_replace('_', ' ', $tz)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="help"><?= e(t('settings.timezone_help')) ?></p>
        </div>
        <button class="button is-primary"><?= e(t('settings.save_profile')) ?></button>
      </form>
    </div>

    <div class="panel-card settings-panel">
      <p class="panel-title"><?= e(t('settings.change_password')) ?></p>
      <form method="post" action="/settings">
        <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="password">
        <div class="field"><input class="input" type="password" name="current_password" placeholder="<?= e(t('settings.current_password')) ?>" required></div>
        <div class="field"><input class="input" type="password" name="new_password" placeholder="<?= e(t('settings.new_password_placeholder')) ?>" required minlength="8"></div>
        <button class="button is-dark-btn"><?= e(t('settings.change_btn')) ?></button>
      </form>
    </div>
  </div>

  <div class="column is-half">
    <div class="panel-card settings-panel">
      <p class="panel-title"><?= e(t('settings.channels')) ?></p>
      <form method="post" action="/settings">
        <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="email_toggle">
        <label class="checkbox">
          <input type="checkbox" name="email_notifications" value="1" <?= (int)$user['email_notifications'] === 1 ? 'checked' : '' ?>>
          <?= e(t('settings.email_notifications')) ?>
        </label>
        <div class="field mt-2">
          <label class="radio"><input type="radio" name="notify_digest" value="0" <?= (int)$user['notify_digest'] === 0 ? 'checked' : '' ?>> <?= e(t('settings.instant')) ?></label>
          <label class="radio"><input type="radio" name="notify_digest" value="1" <?= (int)$user['notify_digest'] === 1 ? 'checked' : '' ?>> <?= e(t('settings.digest')) ?></label>
        </div>
        <button class="button is-primary is-small"><?= e(t('settings.save')) ?></button>
      </form>
    </div>

    <div class="panel-card settings-panel">
      <p class="panel-title"><?= e(t('settings.telegram')) ?></p>
      <?php if ((string)config('telegram.bot_username') === ''): ?>
        <p class="is-size-7" style="color:var(--muted);"><?= e(t('settings.telegram_unconfigured')) ?></p>
      <?php elseif ((int)$user['telegram_verified'] === 1): ?>
        <p class="is-size-7 mb-2"><?= e(t('settings.linked', [$user['telegram_chat_id']])) ?></p>
        <form method="post" action="/settings">
          <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
          <input type="hidden" name="action" value="telegram_unlink">
          <button class="button is-danger is-light is-small" data-confirm="<?= e(t('settings.unlink_confirm')) ?>"><?= e(t('settings.unlink')) ?></button>
        </form>
      <?php else: ?>
        <p class="is-size-7"><?= e(t('settings.telegram_help')) ?></p>
        <p class="mb-2"><a class="button is-link is-small" href="<?= e($tg_link) ?>" target="_blank" rel="noopener"><?= e($tg_link) ?></a></p>
        <p class="is-size-7" style="color:var(--muted);"><?= e(t('settings.telegram_or', ['/start ' . substr($tg_link, strpos($tg_link, '=') + 1)])) ?></p>
      <?php endif; ?>
    </div>

    <div class="panel-card settings-panel" id="notification-history">
      <p class="panel-title"><?= e(t('settings.history')) ?></p>
      <div hx-get="/settings/notifications" hx-trigger="load" hx-swap="innerHTML">
        <p class="is-size-7" style="color:var(--muted);"><?= e(t('settings.loading')) ?></p>
      </div>
    </div>
  </div>
</div>

  </div>
</section>
