<?php
/** @var array $notifications */
?>
<?php if (empty($notifications)): ?>
  <div class="notification is-light"><?= e(t('nt.none')) ?></div>
<?php else: ?>
  <table class="table is-fullwidth is-hoverable">
    <thead><tr><th><?= e(t('nt.tender')) ?></th><th><?= e(t('nt.channel')) ?></th><th><?= e(t('nt.status')) ?></th><th><?= e(t('nt.when')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($notifications as $n): ?>
        <tr>
          <td><a href="/tenders/<?= (int)$n['tender_id'] ?>"><?= e($n['tender_title']) ?></a></td>
          <td><span class="tag <?= $n['channel'] === 'email' ? 'is-link' : 'is-success' ?> is-light"><?= e($n['channel']) ?></span></td>
          <td>
            <span class="tag is-small <?= $n['status'] === 'sent' ? 'is-success' : ($n['status'] === 'failed' ? 'is-danger' : 'is-warning') ?>">
              <?= e($n['status']) ?>
            </span>
            <?php if (!empty($n['error'])): ?><span class="is-size-7 has-text-danger"><?= e($n['error']) ?></span><?php endif; ?>
          </td>
          <td class="is-size-7"><?= e($n['sent_at'] ?? $n['created_at'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
