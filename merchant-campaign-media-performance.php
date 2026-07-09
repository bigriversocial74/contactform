<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Campaign Media Performance | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/campaign-embed-analytics.css'];
$page_scripts = ['/assets/js/campaign-media-performance.js','/assets/js/saved-segment-action-center-links.js'];
$user = mg_current_user();
$selectedCampaign = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string)($_GET['campaign'] ?? ''))) ?: '';
$selectedDays = (int)($_GET['days'] ?? 30);
if (!in_array($selectedDays, [7, 30, 90, 180], true)) $selectedDays = 30;

$merchantNav = [
  'overview' => ['Overview','Workspace health','/merchant.php','Overview'],
  'campaigns' => ['Campaigns','Offers, embeds, followups','/merchant-campaigns.php','Engage'],
  'campaign_media_performance' => ['Media Performance','Watch/listen drilldown','/merchant-campaign-media-performance.php','Engage'],
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
    $appSidebarNav[$key] = ['section' => $item[3] ?? '', 'label' => $item[0], 'detail' => $item[1], 'href' => $item[2], 'visible' => true, 'active' => $key === 'campaign_media_performance'];
}
$appSidebarVariant = 'merchant';
$appSidebarLabel = 'Merchant';
$appSidebarActive = 'campaign_media_performance';
$appSidebarCompact = true;
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app mg-embed-analytics-app" data-merchant-app data-merchant-view="campaign_media_performance" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <div class="mg-embed-analytics-shell" data-campaign-media-performance data-selected-campaign="<?= htmlspecialchars($selectedCampaign, ENT_QUOTES, 'UTF-8') ?>" data-selected-days="<?= (int)$selectedDays ?>">
      <?php if (!$user): ?>
        <section class="mg-embed-analytics-panel">
          <span class="mg-eyebrow">Merchant Analytics</span>
          <h1>Campaign Media Performance</h1>
          <p>Sign in to review Watch and Listen media reward performance.</p>
          <a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a>
        </section>
      <?php else: ?>
        <section class="mg-embed-analytics-hero">
          <div>
            <span class="mg-eyebrow">Saved CRM Media Segments v1</span>
            <h1 data-media-title>Watch / Listen Performance</h1>
            <p data-media-description>Review, save, reopen, export, and act on reusable dynamic Watch/Listen CRM audience segments.</p>
          </div>
          <form class="mg-embed-analytics-filters" data-media-performance-filters>
            <label>Campaign
              <input name="campaign" data-media-campaign-input placeholder="campaign slug or id" value="<?= htmlspecialchars($selectedCampaign, ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label>Window
              <select name="days" data-media-days>
                <option value="7" <?= $selectedDays === 7 ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30" <?= $selectedDays === 30 ? 'selected' : '' ?>>Last 30 days</option>
                <option value="90" <?= $selectedDays === 90 ? 'selected' : '' ?>>Last 90 days</option>
                <option value="180" <?= $selectedDays === 180 ? 'selected' : '' ?>>Last 180 days</option>
              </select>
            </label>
            <div class="mg-embed-analytics-actions">
              <button class="mg-btn mg-btn-primary" type="submit">Refresh detail</button>
              <a class="mg-btn mg-btn-ghost" href="/merchant-campaigns.php">Campaigns</a>
              <a class="mg-btn mg-btn-soft" href="/merchant-campaign-embed-analytics.php" data-media-embed-analytics-link>Embed Analytics</a>
            </div>
          </form>
        </section>

        <section class="mg-embed-analytics-alert" data-media-alert hidden></section>

        <section class="mg-embed-analytics-stats" data-media-stats>
          <article><b>—</b><span>Contacts</span></article>
          <article><b>—</b><span>Starts</span></article>
          <article><b>—</b><span>Progress</span></article>
          <article><b>—</b><span>Rewards</span></article>
          <article><b>—</b><span>Claims</span></article>
        </section>

        <section class="mg-embed-analytics-panel">
          <div class="mg-panel-head">
            <div><span class="mg-eyebrow">Follow-Up Actions</span><h2>Segment and act on media engagement</h2></div>
          </div>
          <div class="mg-embed-analytics-filters" data-media-action-controls>
            <label>Behavior
              <select data-media-segment>
                <option value="all">All contacts</option>
                <option value="started_incomplete">Started, did not finish</option>
                <option value="milestone_unclaimed">Milestone hit, not claimed</option>
                <option value="claimed_unredeemed">Claimed, not redeemed</option>
                <option value="redeemed">Redeemed / completed</option>
                <option value="no_activity">No tracked activity</option>
              </select>
            </label>
            <label>Search
              <input data-media-search placeholder="Name, email, phone, source">
            </label>
            <div class="mg-embed-analytics-actions">
              <button class="mg-btn mg-btn-primary" type="button" data-media-save-segment>Save Segment</button>
              <a class="mg-btn mg-btn-soft" href="#" data-media-export>Export visible CSV</a>
              <a class="mg-btn mg-btn-soft" href="#" data-media-export-all>Export all CSV</a>
              <a class="mg-btn mg-btn-ghost" href="#" data-media-crm-campaign>Open CRM Campaign</a>
            </div>
          </div>
          <div class="mg-embed-analytics-events" data-media-segment-summary></div>
        </section>

        <section class="mg-embed-analytics-panel" data-media-saved-segment-panel>
          <div class="mg-panel-head">
            <div><span class="mg-eyebrow">Saved Segments</span><h2>Reusable media CRM audiences</h2><p>Dynamic segments refresh from the current campaign rules, not static snapshots.</p></div>
            <div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-media-refresh-segments>Refresh segments</button></div>
          </div>
          <div class="mg-embed-analytics-events" data-media-saved-segments><article><b>Loading saved segments...</b><span>Import the SQL migration if this stays empty.</span></article></div>
        </section>

        <section class="mg-embed-analytics-grid">
          <article class="mg-embed-analytics-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Campaign</span><h2>Campaign summary</h2></div></div>
            <div class="mg-embed-analytics-events" data-media-summary></div>
          </article>
          <article class="mg-embed-analytics-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Attribution</span><h2>Embed origins</h2></div></div>
            <div class="mg-embed-analytics-origins" data-media-origins></div>
          </article>
        </section>

        <section class="mg-embed-analytics-panel">
          <div class="mg-panel-head"><div><span class="mg-eyebrow">Customers</span><h2>Contact-level media progress</h2></div></div>
          <div class="mg-embed-analytics-table-wrap"><table class="mg-embed-analytics-table" data-media-contact-table></table></div>
        </section>

        <section class="mg-embed-analytics-panel">
          <div class="mg-panel-head"><div><span class="mg-eyebrow">Activity</span><h2>Recent media events</h2></div></div>
          <div class="mg-embed-analytics-events" data-media-events></div>
        </section>
      <?php endif; ?>
    </div>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
