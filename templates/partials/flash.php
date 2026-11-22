<?php $byType = ['success' => 'is-success', 'error' => 'is-danger']; ?>
<div class="notification <?= $byType[$f['type']] ?? 'is-info' ?>">
  <button class="delete"></button><?= e($f['message']) ?>
</div>
