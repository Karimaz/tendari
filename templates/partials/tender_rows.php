<?php /** @var array $results */ foreach ($results as $t): ?>
          <tr>
            <td>
              <a href="/tenders/<?= (int)$t['id'] ?>" class="has-text-weight-semibold"><?= e($t['title']) ?></a>
              <?php if ($t['status'] !== 'open'): ?>
                <span class="tag is-light"><?= e($t['status']) ?></span>
              <?php endif; ?>
            </td>
            <td class="is-size-7"><?= e($t['organization'] ?: '—') ?></td>
            <td class="is-size-7"><?= e($t['source_name'] ?? '') ?></td>
            <td>
              <?php foreach (json_list($t['countries']) as $c): ?>
                <span class="tag is-small is-info is-light"><?= e($c) ?></span>
              <?php endforeach; ?>
            </td>
            <td><span class="tag is-small"><?= e($t['funding_type'] ?? 'unknown') ?></span></td>
            <td class="is-size-7">
              <?php if (!empty($t['deadline_at'])): ?>
                <span data-deadline="<?= e($t['deadline_at']) ?>"><?= e($t['deadline_at']) ?></span>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
