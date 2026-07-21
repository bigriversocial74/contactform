<?php
declare(strict_types=1);
?>
<?php if ($handoffs === []): ?>
  <section class="mg-drafts-empty"><strong>No native handoffs yet.</strong><p>After an approved agent draft is converted into an inactive Microgifter draft, its canonical status will appear here.</p></section>
<?php else: ?>
  <section class="mg-handoff-list">
    <?php foreach ($handoffs as $handoff): $native=(array)$handoff['native']; $observation=(array)$handoff['observation']; ?>
      <article class="mg-handoff-card is-<?= mg_e((string)$native['state_class']) ?>">
        <header><div><span><?= mg_e(strtoupper((string)$handoff['draft_type'])) ?></span><h2><?= mg_e((string)$handoff['title']) ?></h2><p><?= mg_e((string)$handoff['summary']) ?></p></div><strong><?= mg_e(ucwords(str_replace('_',' ',(string)$native['state_class']))) ?></strong></header>
        <dl>
          <div><dt>Native state</dt><dd><?= mg_e((string)$native['state']) ?></dd></div>
          <div><dt>Native ID</dt><dd><code><?= mg_e((string)($native['id'] ?? 'Unavailable')) ?></code></dd></div>
          <div><dt>Native updated</dt><dd><?= mg_e((string)($native['updated_at'] ?? 'Not reported')) ?></dd></div>
          <div><dt>Last checked</dt><dd><?= mg_e((string)($observation['observed_at'] ?? 'Not checked')) ?></dd></div>
        </dl>
        <div class="mg-handoff-actions">
          <?php if (!empty($native['url'])): ?><a href="<?= mg_e((string)$native['url']) ?>">Open native draft</a><?php endif; ?>
          <form method="post"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><input type="hidden" name="action" value="refresh_status"><input type="hidden" name="conversion_id" value="<?= mg_e((string)$handoff['conversion']['id']) ?>"><button type="submit">Refresh status</button></form>
        </div>
        <details><summary>Status evidence</summary><pre><?= mg_e(json_encode($native['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></pre><p>Receipt: <code><?= mg_e((string)($observation['receipt_id'] ?? 'No receipt yet')) ?></code></p></details>
      </article>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
