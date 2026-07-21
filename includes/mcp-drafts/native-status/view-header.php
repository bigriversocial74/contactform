<?php
declare(strict_types=1);
?>
<section class="mg-app-shell mg-agent-drafts-shell">
  <?php require dirname(__DIR__, 2) . '/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-drafts-workspace mg-handoffs-workspace">
    <header class="mg-drafts-hero">
      <div><span class="mg-drafts-eyebrow">Native handoff receipts · Phase 3C</span><h1>Agent handoffs</h1><p>See what happened after an approved external-agent draft became an inactive Microgifter draft. Status checks read the canonical native object and never change it.</p></div>
      <div class="mg-handoff-hero-actions"><a class="mg-drafts-link" href="/account-agent-drafts.php">Review agent drafts</a><a class="mg-drafts-link is-secondary" href="/account-ai-connections.php">AI connections</a></div>
    </header>
    <?php if ($notice !== ''): ?><div class="mg-drafts-alert is-success"><?= mg_e($notice) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="mg-drafts-alert is-error"><?= mg_e($errorMessage) ?></div><?php endif; ?>
    <section class="mg-handoff-summary" aria-label="Native handoff status summary">
      <?php foreach (['draft'=>'Draft','review'=>'In review','active'=>'Active','completed'=>'Completed','archived'=>'Archived','missing'=>'Missing'] as $key=>$label): ?>
        <article><strong><?= (int)($summary[$key] ?? 0) ?></strong><span><?= mg_e($label) ?></span></article>
      <?php endforeach; ?>
    </section>
