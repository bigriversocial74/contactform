<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

mg_require_auth();

$page_title = 'My Saves | Microgifter';
$page_section = 'agent';
$header_mode = 'account';
$agent_tab = 'saves';
$page_styles = [
    '/assets/css/user-lists.css',
    '/assets/css/personal-agent-opportunity-actions.css?v=1.0.0',
    '/assets/css/personal-agent-my-saves.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/saved-opportunities.js?v=1.2.0',
    '/assets/js/personal-agent-attribution-runtime.js?v=1.0.0',
];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-user-lists-shell mg-user-saves-shell" data-user-saves-page>
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <div class="mg-user-lists-main">
    <header class="mg-user-lists-hero">
      <div>
        <span class="mg-user-lists-kicker">Personal Gifting Agent</span>
        <h1>My Saves</h1>
        <p>Review products, campaigns, merchants, experiences, and rewards saved from your Personal Agent recommendations.</p>
      </div>
      <div class="mg-user-lists-hero-actions">
        <a class="mg-btn mg-user-lists-secondary-action" href="/lists.php">My Lists</a>
      </div>
    </header>

    <section class="mg-saved-opportunities mg-saved-opportunities-page" data-saved-opportunities aria-label="Saved Personal Agent opportunities">
      <div class="mg-saves-toolbar">
        <div class="mg-saves-filter-tabs" role="group" aria-label="Filter saved items">
          <button type="button" class="is-active" data-saved-opportunity-filter="all" aria-pressed="true">All</button>
          <button type="button" data-saved-opportunity-filter="product" aria-pressed="false">Products</button>
          <button type="button" data-saved-opportunity-filter="campaign" aria-pressed="false">Campaigns</button>
          <button type="button" data-saved-opportunity-filter="merchant" aria-pressed="false">Merchants</button>
          <button type="button" data-saved-opportunity-filter="experience" aria-pressed="false">Experiences</button>
          <button type="button" data-saved-opportunity-filter="reward" aria-pressed="false">Rewards</button>
        </div>
        <span class="mg-saves-status" data-saved-opportunity-status aria-live="polite">Loading saved items…</span>
      </div>
      <div class="mg-saved-opportunity-grid" data-saved-opportunity-grid>
        <div class="mg-saved-opportunities-empty">Loading saved items…</div>
      </div>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>