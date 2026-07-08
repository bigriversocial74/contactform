<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Campaign Embed Analytics | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/campaign-embed-analytics.css'];
$page_scripts = ['/assets/js/campaign-embed-analytics.js'];
$user = mg_current_user();
$selectedCampaign = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string)($_GET['campaign'] ?? ''))) ?: '';
$selectedDays = (int)($_GET['days'] ?? 30);
if (!in_array($selectedDays, [7, 30, 90], true)) $selectedDays = 30;

$merchantNav = [
  'overview' => ['Overview','Workspace health','/merchant.php','Overview'],
  'campaigns' => ['Campaigns','Offers, embeds, followups','/merchant-campaigns.php','Engage'],
  'campaign_embed_analytics' => ['Embed Analytics','Website embed performance','/merchant-campaign-embed-analytics.php','Engage'],
  'campaign_embed_qa' => ['Embed QA','Runtime smoke checks','/merchant-campaign-embed-qa.php','Engage'],
  'notifications' => ['Notifications','Tips, voucher messages, alerts','/merchant-notifications.php','Overview'],
  'agent_chat' => ['Agent Chat','Merchant agent feed','/merchant-agent-chat.php','Engage'],
  'claims' => ['Claims','Verification and redemption','/merchant-claims.php','Commerce'],
  'stamps' => ['Stamp Ledger','Sends and balance','/merchant-stamps.php','Finance'],
  'settings' => ['Settings','Business configuration','/merchant-settings.php','Manage'],
];
$appSidebarNav = [];
foreach ($merchantNav as $key => $item) {
    $appSidebarNav[$key] = ['section' => $item[3] ?? '', 'label' => $item[0], 'detail' => $item[1], 'href' => $item[2], 'visible' => true, 'active' => $key === 'campaign_embed_analytics'];
}
$appSidebarVariant = 'merchant';
$appSidebarLabel = 'Merchant';
$appSidebarActive = 'campaign_embed_analytics';
$appSidebarCompact = true;
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app mg-embed-analytics-app" data-merchant-app data-merchant-view="campaign_embed_analytics" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <div class="mg-embed-analytics-shell" data-campaign-embed-analytics data-selected-campaign="<?= htmlspecialchars($selectedCampaign, ENT_QUOTES, 'UTF-8') ?>" data-selected-days="<?= (int)$selectedDays ?>">
      <?php if (!$user): ?>
        <section class="mg-embed-analytics-panel">
          <span class="mg-eyebrow">Merchant Analytics</span>
          <h1>Campaign Embed Analytics</h1>
          <p>Sign in to review website embed performance.</p>
          <a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a>
        </section>
      <?php else: ?>
        <section class="mg-embed-analytics-hero">
          <div>
            <span class="mg-eyebrow">Campaign Embed Analytics v3.1</span>
            <h1>Website Embed Performance</h1>
            <p>Track embed loads, button opens, submissions, invalid attempts, errors, conversion rate, top domains, and recent website activity.</p>
          </div>
          <form class="mg-embed-analytics-filters" data-embed-analytics-filters>
            <label>Campaign
              <select name="campaign" data-embed-analytics-campaign>
                <option value="">All campaigns</option>
              </select>
            </label>
            <label>Window
              <select name="days" data-embed-analytics-days>
                <option value="7" <?= $selectedDays === 7 ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30" <?= $selectedDays === 30 ? 'selected' : '' ?>>Last 30 days</option>
                <option value="90" <?= $selectedDays === 90 ? 'selected' : '' ?>>Last 90 days</option>
              </select>
            </label>
            <div class="mg-embed-analytics-actions">
              <button class="mg-btn mg-btn-primary" type="submit">Refresh analytics</button>
              <button class="mg-btn mg-btn-soft" type="button" data-copy-analytics-link>Copy analytics link</button>
              <a class="mg-btn mg-btn-ghost" href="/merchant-campaigns.php">Campaigns</a>
            </div>
            <div class="mg-embed-export-actions" data-embed-export-actions>
              <a class="mg-btn mg-btn-soft" href="#" data-export-analytics="campaigns">Export campaign CSV</a>
              <a class="mg-btn mg-btn-soft" href="#" data-export-analytics="domains">Export domains CSV</a>
              <a class="mg-btn mg-btn-soft" href="#" data-export-analytics="events">Export events CSV</a>
            </div>
          </form>
        </section>

        <section class="mg-embed-analytics-alert" data-embed-analytics-alert hidden></section>

        <section class="mg-embed-analytics-stats" data-embed-analytics-stats>
          <article><b>—</b><span>Loaded</span></article>
          <article><b>—</b><span>Opened</span></article>
          <article><b>—</b><span>Submitted</span></article>
          <article><b>—</b><span>Conversion</span></article>
          <article><b>—</b><span>Errors</span></article>
        </section>

        <section class="mg-embed-analytics-grid">
          <article class="mg-embed-analytics-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Trend</span><h2>Event timeline</h2></div></div>
            <div class="mg-embed-analytics-timeline" data-embed-analytics-timeline></div>
          </article>
          <article class="mg-embed-analytics-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Origins</span><h2>Top embed domains</h2></div></div>
            <div class="mg-embed-analytics-origins" data-embed-analytics-origins></div>
          </article>
        </section>

        <section class="mg-embed-analytics-panel">
          <div class="mg-panel-head"><div><span class="mg-eyebrow">Campaigns</span><h2>Campaign comparison</h2></div></div>
          <div class="mg-embed-analytics-table-wrap"><table class="mg-embed-analytics-table" data-embed-analytics-campaign-table></table></div>
        </section>

        <section class="mg-embed-analytics-panel">
          <div class="mg-panel-head"><div><span class="mg-eyebrow">Activity</span><h2>Recent embed events</h2></div></div>
          <div class="mg-embed-analytics-events" data-embed-analytics-events></div>
        </section>
      <?php endif; ?>
    </div>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
