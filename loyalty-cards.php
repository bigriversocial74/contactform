<?php
require_once __DIR__ . '/includes/app.php';

$page_title = 'Loyalty Cards | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'loyalty-cards';
$suppress_footer = true;
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/gift-action-center.css',
    '/assets/css/loyalty-cards.css',
];
$page_scripts = [
    '/assets/js/loyalty-cards.js',
];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-loyalty-cards-page" data-loyalty-cards-page>
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-loyalty-workspace">
    <section class="mg-loyalty-shell" aria-label="Saved loyalty cards">
      <header class="mg-loyalty-hero">
        <span>Saved Cards</span>
        <h1>Loyalty Cards</h1>
        <p>Save active stamp-card campaigns, track verified progress, and jump back into the merchant card when you are ready for the next visit.</p>
      </header>

      <div class="mg-loyalty-status" data-loyalty-cards-status role="status" aria-live="polite">Loading saved loyalty cards…</div>
      <section class="mg-loyalty-grid" data-loyalty-cards-list aria-label="Saved loyalty card list"></section>

      <section class="mg-loyalty-empty" data-loyalty-cards-empty hidden>
        <h2>No saved loyalty cards yet.</h2>
        <p>Open a merchant Stamp Card campaign and tap the star / Save Card button to keep it here.</p>
        <a class="mg-btn mg-btn-primary" href="/discover.php">Explore campaigns</a>
      </section>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
