<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
mg_require_auth();
$user = mg_current_user();
$packageContext = mg_user_package_context(null, $user);
$canAdvertising = !empty($packageContext['merchant_access']);

$page_title = 'My Saves | Microgifter';
$page_section = 'agent';
$header_mode = 'account';
$agent_tab = 'saves';
$page_styles = [
    '/assets/css/user-lists.css',
    '/assets/css/personal-agent-chat-history.css?v=1.2.0',
    '/assets/css/personal-agent-opportunity-actions.css?v=1.0.0',
    '/assets/css/personal-agent-my-saves.css?v=1.0.0',
    '/assets/css/design-studio-advertising-workflow-v2.css?v=2.0.0',
];
$page_scripts = [
    '/assets/js/personal-agent-chat-history.js?v=1.2.0',
    '/assets/js/saved-opportunities.js?v=1.2.0',
    '/assets/js/personal-agent-attribution-runtime.js?v=1.0.0',
];
if ($canAdvertising) $page_scripts[] = '/assets/js/saved-advertising.js?v=2.0.0';

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-user-lists-shell mg-user-saves-shell" data-user-saves-page>
  <?php require __DIR__ . '/includes/personal-agent-sidebar.php'; ?>
  <div class="mg-user-lists-main">
    <header class="mg-user-lists-hero">
      <div><span class="mg-user-lists-kicker">Personal Gifting Agent</span><h1>My Saves</h1><p>Review saved recommendations and, for merchant accounts, advertising creatives created in Design Studio.</p></div>
      <div class="mg-user-lists-hero-actions"><a class="mg-btn mg-user-lists-secondary-action" href="/lists.php">My Lists</a><?php if ($canAdvertising): ?><a class="mg-btn mg-user-lists-secondary-action" href="/design-studio.php">Design Studio</a><?php endif; ?></div>
    </header>

    <div class="mg-saves-primary-tabs" role="tablist" aria-label="Saved content sections">
      <button type="button" class="is-active" role="tab" aria-selected="true" data-saves-tab="saved-items">Saved items</button>
      <?php if ($canAdvertising): ?><button type="button" role="tab" aria-selected="false" data-saves-tab="advertising">Advertising</button><?php endif; ?>
    </div>

    <section class="mg-saved-opportunities mg-saved-opportunities-page" data-saved-opportunities data-saves-panel="saved-items" aria-label="Saved Personal Agent opportunities">
      <div class="mg-saves-toolbar"><div class="mg-saves-filter-tabs" role="group" aria-label="Filter saved items"><button type="button" class="is-active" data-saved-opportunity-filter="all" aria-pressed="true">All</button><button type="button" data-saved-opportunity-filter="product" aria-pressed="false">Products</button><button type="button" data-saved-opportunity-filter="campaign" aria-pressed="false">Campaigns</button><button type="button" data-saved-opportunity-filter="merchant" aria-pressed="false">Merchants</button><button type="button" data-saved-opportunity-filter="experience" aria-pressed="false">Experiences</button><button type="button" data-saved-opportunity-filter="reward" aria-pressed="false">Rewards</button></div><span class="mg-saves-status" data-saved-opportunity-status aria-live="polite">Loading saved items…</span></div>
      <div class="mg-saved-opportunity-grid" data-saved-opportunity-grid><div class="mg-saved-opportunities-empty">Loading saved items…</div></div>
    </section>

    <?php if ($canAdvertising): ?>
      <section class="mg-saved-advertising" data-saved-advertising data-saves-panel="advertising" aria-label="Saved merchant advertising creatives" hidden>
        <div class="mg-advertising-toolbar">
          <label><span>Product</span><select data-advertising-filter="product_id"><option value="">All products</option></select></label>
          <label><span>Format</span><select data-advertising-filter="format"><option value="">All formats</option><option value="poster">Poster</option><option value="tent">Table tent</option><option value="square">Square</option><option value="portrait">Portrait</option><option value="story">Story / Reel</option></select></label>
          <label><span>Status</span><select data-advertising-filter="status"><option value="active">Active</option><option value="archived">Archived</option><option value="all">All</option></select></label>
          <label><span>Saved from</span><input type="date" data-advertising-filter="date_from"></label><label><span>Saved to</span><input type="date" data-advertising-filter="date_to"></label>
          <button type="button" class="mg-btn mg-btn-soft" data-advertising-refresh>Refresh</button>
        </div>
        <span class="mg-saves-status" data-advertising-status aria-live="polite">Advertising assets load when this tab opens.</span>
        <div class="mg-advertising-loading" data-advertising-loading hidden>Loading saved advertising…</div>
        <div class="mg-advertising-setup" data-advertising-setup hidden><strong>Advertising asset setup required</strong><p>Import <code>database/20260716_design_studio_advertising_workflow_v2.sql</code>, then refresh.</p></div>
        <div class="mg-advertising-error" data-advertising-error hidden></div>
        <div class="mg-advertising-empty" data-advertising-empty hidden><strong>No saved advertising creatives match these filters.</strong><p>Open Design Studio, preview a creative, and choose Save Creative Asset.</p></div>
        <div class="mg-advertising-grid" data-advertising-grid></div>
      </section>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>