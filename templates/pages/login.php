<div class="auth-card">
  <h1 class="title is-4"><?= e(t('auth.login.title')) ?></h1>
  <form method="post" action="/login">
    <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
    <div class="field">
      <label class="label"><?= e(t('auth.email')) ?></label>
      <input class="input" type="email" name="email" value="<?= e(old('email')) ?>" required>
    </div>
    <div class="field">
      <label class="label"><?= e(t('auth.password')) ?></label>
      <input class="input" type="password" name="password" required>
    </div>
    <button class="button is-primary is-fullwidth mt-3"><?= e(t('auth.login.submit')) ?></button>
  </form>
  <p class="mt-4 is-size-7 has-text-centered"><?= e(t('auth.no_account')) ?> <a href="/register"><strong><?= e(t('auth.create_free')) ?></strong></a></p>
</div>
