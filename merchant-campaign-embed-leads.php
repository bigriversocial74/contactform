<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Campaign Embed Leads | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/campaign-embed-leads.css'];
$page_scripts = ['/assets/js/campaign-embed-leads.js'];
$user = mg_current_user();
$selectedCampaign = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string)($_GET['campaign'] ?? ''))) ?: '';
$selectedOrigin = preg_replace('/[^a-zA-Z0-9_.\-]/', '', trim((string)($_GET['origin_host'] ?? ''))) ?: '';
$selectedSource = preg_replace('/[^a-zA-Z0-9_:\-]/', '', trim((string)($_GET['source'] ?? ''))) ?: '';
$selectedDays = (int)($_GET['days'] ?? 30);
if (!in_array($selectedDays, [7, 30, 90], true)) $selectedDays = 30;

$merchantNav = [
  'overview' => ['Overview','Workspace health','/merchant.php','Overview'],
  'campaigns' => ['Campaigns','Offers, embeds, followups','/merchant-campaigns.php','Engage'],
  'campaign_embed_leads' => ['Embed Leads','Website embed contacts','/merchant-campaign-embed-leads.php','Engage'],
  'campaign_embed_analytics' => ['Embed Analytics','Website embed performance','/merchant-campaign-embed-analytics.php','Engage'],
  'campaign_embed_qa' => ['Embed QA','Runtime smoke checks','/merchant-campaign-embed-qa.php','Engage'],
  'merchant_crm' => ['Merchant CRM','Customers and history','/merchant-crm.php','Engage'],
  'notifications' => ['Notifications','Tips, voucher messages, alerts','/merchant-notifications.php','Overview'],
  'claims' => ['Claims','Verification and redemption','/merchant-claims.php','Commerce'],
  'stamps' => ['Stamp Ledger','Sends and balance','/merchant-stamps.php','Finance'],
  'settings' => ['Settings','Business configuration','/merchant-settings.php','Manage'],
];
$appSidebarNav = [];
foreach ($merchantNav as $key => $item) $appSidebarNav[$key] = ['section' => $item[3] ?? '', 'label' => $item[0], 'detail' => $item[1], 'href' => $item[2], 'visible' => true, 'active' => $key === 'campaign_embed_leads'];
$appSidebarVariant = 'merchant';
$appSidebarLabel = 'Merchant';
$appSidebarActive = 'campaign_embed_leads';
$appSidebarCompact = true;
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app mg-embed-leads-app" data-merchant-app data-merchant-view="campaign_embed_leads" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <div class="mg-embed-leads-shell" data-campaign-embed-leads data-selected-campaign="<?= htmlspecialchars($selectedCampaign, ENT_QUOTES, 'UTF-8') ?>" data-selected-days="<?= (int)$selectedDays ?>" data-selected-origin="<?= htmlspecialchars($selectedOrigin, ENT_QUOTES, 'UTF-8') ?>" data-selected-source="<?= htmlspecialchars($selectedSource, ENT_QUOTES, 'UTF-8') ?>">
      <?php if (!$user): ?>
        <section class="mg-embed-leads-panel">
          <span class="mg-eyebrow">Merchant Leads</span>
          <h1>Website Embed Leads</h1>
          <p>Sign in to review campaign contacts captured from website embeds.</p>
          <a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a>
        </section>
      <?php else: ?>
        <section class="mg-embed-leads-hero">
          <div>
            <span class="mg-eyebrow">Campaign Embed Performance v4.4</span>
            <h1>Conversion Quality Layer</h1>
            <p>See which embeds are actually working: lead quality, follow-up readiness, top conversion sources, and merchant recommendations from existing attribution data.</p>
            <div class="mg-embed-leads-hero-actions">
              <a class="mg-btn mg-btn-soft" href="/merchant-campaign-embed-qa.php">Run Embed QA</a>
              <a class="mg-btn mg-btn-ghost" href="/merchant-campaign-embed-analytics.php">Embed Analytics</a>
              <a class="mg-btn mg-btn-ghost" href="/merchant-notifications.php">Notifications</a>
            </div>
          </div>
          <form class="mg-embed-leads-filters" data-embed-leads-filters>
            <label>Campaign
              <select name="campaign" data-embed-leads-campaign><option value="">All campaigns</option></select>
            </label>
            <label>Window
              <select name="days" data-embed-leads-days>
                <option value="7" <?= $selectedDays === 7 ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30" <?= $selectedDays === 30 ? 'selected' : '' ?>>Last 30 days</option>
                <option value="90" <?= $selectedDays === 90 ? 'selected' : '' ?>>Last 90 days</option>
              </select>
            </label>
            <label>Domain
              <input name="origin_host" data-embed-leads-origin value="<?= htmlspecialchars($selectedOrigin, ENT_QUOTES, 'UTF-8') ?>" placeholder="example.com">
            </label>
            <label>Source
              <input name="source" data-embed-leads-source value="<?= htmlspecialchars($selectedSource, ENT_QUOTES, 'UTF-8') ?>" placeholder="contest_entry, qr_scan">
            </label>
            <div class="mg-embed-leads-actions">
              <button class="mg-btn mg-btn-primary" type="submit">Refresh leads</button>
              <button class="mg-btn mg-btn-soft" type="button" data-embed-leads-reset>Clear filters</button>
              <a class="mg-btn mg-btn-ghost" href="/merchant-campaigns.php">Campaigns</a>
              <a class="mg-btn mg-btn-ghost" href="#" data-embed-leads-export>Export CSV</a>
            </div>
            <p class="mg-embed-leads-filter-note" data-embed-leads-filter-summary>Loading lead attribution filters…</p>
          </form>
        </section>

        <section class="mg-embed-leads-alert" data-embed-leads-alert hidden></section>
        <section class="mg-embed-leads-notification-badge" data-embed-leads-notification-badge hidden></section>
        <section class="mg-embed-leads-stats" data-embed-leads-stats>
          <article><b>—</b><span>Total Embed Leads</span></article>
          <article><b>—</b><span>New Contacts</span></article>
          <article><b>—</b><span>Ready Follow-Up</span></article>
          <article><b>—</b><span>Avg Quality</span></article>
        </section>

        <section class="mg-embed-performance-panel mg-embed-leads-panel">
          <div class="mg-panel-head"><div><span class="mg-eyebrow">Performance</span><h2>Conversion quality insights</h2></div></div>
          <div class="mg-embed-performance-cards" data-embed-performance-insights></div>
          <div class="mg-embed-performance-split">
            <div><h3>Lead quality mix</h3><div class="mg-embed-quality-breakdown" data-embed-quality-breakdown></div></div>
            <div><h3>Merchant recommendations</h3><div class="mg-embed-recommendations" data-embed-recommendations></div></div>
          </div>
        </section>

        <section class="mg-embed-leads-value-grid">
          <article class="mg-embed-leads-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Campaign Value</span><h2>Campaign attribution summary</h2></div></div>
            <div class="mg-embed-leads-campaigns" data-embed-leads-campaign-summaries></div>
          </article>
          <aside class="mg-embed-leads-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Pages</span><h2>Top lead pages</h2></div></div>
            <div class="mg-embed-leads-pages" data-embed-leads-pages></div>
          </aside>
        </section>

        <section class="mg-embed-leads-grid">
          <article class="mg-embed-leads-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Leads</span><h2>Recent website embed leads</h2></div></div>
            <div class="mg-embed-leads-table-wrap"><table class="mg-embed-leads-table" data-embed-leads-table></table></div>
          </article>
          <aside class="mg-embed-leads-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Domains</span><h2>Top lead domains</h2></div></div>
            <div class="mg-embed-leads-domains" data-embed-leads-domains></div>
          </aside>
        </section>

        <aside class="mg-embed-leads-drawer" data-embed-leads-drawer hidden>
          <div class="mg-embed-leads-drawer-backdrop" data-embed-leads-close></div>
          <article class="mg-embed-leads-drawer-panel">
            <button class="mg-embed-leads-drawer-close" type="button" data-embed-leads-close aria-label="Close lead detail">×</button>
            <div data-embed-leads-drawer-content></div>
          </article>
        </aside>
      <?php endif; ?>
    </div>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
