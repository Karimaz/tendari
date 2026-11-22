<?php /** @var array $users */
render_partial('partials/admin_tabs', ['active' => 'users']); ?>
<section class="page-section">
  <div class="container wide">
<h1 class="title is-4">Users</h1>
<table class="table is-fullwidth is-size-7">
  <thead><tr><th>Email</th><th>Name</th><th>Registered</th><th>Last login</th><th>Verified</th><th>Telegram</th><th>Active</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['name']) ?></td>
        <td><?= e($u['created_at']) ?></td>
        <td><?= e($u['last_login_at'] ?? 'never') ?></td>
        <td><?= (int)$u['email_verified'] ? '✓' : '✗' ?></td>
        <td><?= (int)$u['telegram_verified'] ? '✓ ' . e($u['telegram_chat_id'] ?? '') : '✗' ?></td>
        <td><?= (int)$u['is_active'] ? '<span class="tag is-success">active</span>' : '<span class="tag is-danger">disabled</span>' ?></td>
        <td>
          <form method="post" action="/admin/users/<?= (int)$u['id'] ?>/toggle-active">
            <input type="hidden" name="csrf" value="<?= e(\Tendari\Core\Auth::csrfToken()) ?>">
            <button class="button is-small"><?= (int)$u['is_active'] ? 'Disable' : 'Activate' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

  </div>
</section>
