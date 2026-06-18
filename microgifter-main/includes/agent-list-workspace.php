<?php
$user = mg_current_user();
$listMode = $agent_tab ?? 'inbox';
?>
<section class="mg-app-shell mg-agent-app mg-agent-items-app" data-agent-items-app data-list-mode="<?= mg_e($listMode) ?>">
  <?php require __DIR__ . '/agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-agent-list-workspace" data-agent-items-center>
    <section class="mg-app-panel mg-agent-list-panel">
      <div class="mg-agent-list-body" aria-live="polite">
        <div class="mg-agent-list-empty"><strong>Loading activity…</strong></div>
      </div>
    </section>
  </div>

  <aside class="mg-gift-preview" data-gift-preview aria-hidden="true">
    <button class="mg-gift-preview-close-floating" type="button" data-preview-close aria-label="Close loaded item">×</button>
    <div class="mg-gift-preview-head">
      <div><span>Loaded item</span><strong data-preview-id>Product</strong></div>
      <div class="mg-gift-preview-head-actions"><span class="mg-gift-preview-counter" data-preview-counter>1 / 1</span></div>
    </div>
    <div class="mg-gift-preview-body" data-preview-body>
      <img data-preview-avatar src="/assets/images/default-avatar.svg" alt="User profile picture">
      <h2 data-preview-title>Gift item</h2><p data-preview-description></p>
      <dl>
        <div><dt>Sent from</dt><dd data-preview-sent-from></dd></div>
        <div><dt>Recipient</dt><dd data-preview-recipient></dd></div>
        <div><dt>Timestamp</dt><dd data-preview-time></dd></div>
        <div><dt>Gift type</dt><dd data-preview-type></dd></div>
        <div><dt>Value</dt><dd data-preview-value></dd></div>
      </dl>
      <div class="mg-gift-preview-card"><span>Microgifter card</span><strong data-preview-card-title>Gift loaded</strong><small data-preview-card-value>$0.00</small></div>
    </div>
    <nav class="mg-gift-preview-nav" aria-label="Loaded gift navigation">
      <button type="button" data-preview-prev aria-label="Previous gift">↑</button>
      <button type="button" data-preview-next aria-label="Next gift">↓</button>
    </nav>
  </aside>
</section>

<div class="mg-item-modal" data-item-modal aria-hidden="true">
  <div class="mg-item-modal-backdrop" data-modal-close></div>
  <section class="mg-item-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-item-modal-title">
    <header><div><span data-modal-eyebrow>Item action</span><h2 id="mg-item-modal-title" data-modal-title>Action</h2></div><button type="button" data-modal-close aria-label="Close modal">×</button></header>
    <div class="mg-item-modal-body" data-modal-body></div>
  </section>
</div>