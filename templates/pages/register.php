<div class="auth-card">
  <h1 class="title is-4"><?= e(t('auth.register.title')) ?></h1>
  <form method="post" action="/register">
    <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
    <div class="field">
      <label class="label"><?= e(t('auth.name')) ?></label>
      <input class="input" name="name" value="<?= e(old('name')) ?>" required maxlength="100">
    </div>
    <div class="field">
      <label class="label"><?= e(t('auth.email')) ?></label>
      <input class="input" type="email" name="email" value="<?= e(old('email')) ?>" required>
    </div>
    <div class="field">
      <label class="label"><?= e(t('auth.password')) ?></label>
      <input class="input" type="password" name="password" required minlength="8">
      <p class="help"><?= e(t('auth.password_help')) ?></p>
    </div>
    <button class="button is-primary is-fullwidth mt-3"><?= e(t('auth.register.submit')) ?></button>
  </form>
  <p class="mt-4 is-size-7 has-text-centered"><?= e(t('auth.free_forever')) ?> · <?= e(t('auth.have_account')) ?> <a href="/login"><strong><?= e(t('auth.login.submit')) ?></strong></a></p>
</div>
