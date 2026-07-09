<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Saved Segment Action Center | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/campaign-embed-analytics.css','/assets/css/merchant-crm.css'];
$page_scripts = ['/assets/js/crm-segment-action-center.js'];
$user = mg_current_user();
$selectedSegment = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string)($_GET['segment'] ?? $_GET['saved_segment'] ?? ''))) ?: '';

$merchantNav = [
  'overview' => ['Overview','Workspace health','/merchant.php','Overview'],
  'crm' => ['CRM','Contacts and saved segments','/merchant-crm.php','Engage'],
  'campaigns' => ['Campaigns','Offers, embeds, followups','/merchant-campaigns.php','Engage'],
  'campaign_media_performance' => ['Media Performance','Watch/listen drilldown','/merchant-campaign-media-performance.php','Engage'],
  'campaign_embed_analytics' => ['Embed Analytics','Website embed performance','/merchant-campaign-embed-analytics.php','Engage'],
  'notifications' => ['Notifications','Tips, voucher messages, alerts','/merchant-notifications.php','Overview'],
  'claims' => ['Claims','Verification and redemption','/merchant-claims.php','Commerce'],
  'stamps' => ['Stamp Ledger','Sends and balance','/merchant-stamps.php','Finance'],
  'settings' => ['Settings','Business configuration','/merchant-settings.php','Manage'],
];
$appSidebarNav = [];
foreach ($merchantNav as $key => $item) {
    $appSidebarNav[$key] = ['section' => $item[3] ?? '', 'label' => $item[0], 'detail' => $item[1], 'href' => $item[2], 'visible' => true, 'active' => $key === 'crm'];
}
$appSidebarVariant = 'merchant';
$appSidebarLabel = 'Merchant';
$appSidebarActive = 'crm';
$appSidebarCompact = true;
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app mg-embed-analytics-app" data-merchant-app data-merchant-view="crm_segment_action_center" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <div class="mg-embed-analytics-shell" data-crm-segment-action-center data-selected-segment="<?= htmlspecialchars($selectedSegment, ENT_QUOTES, 'UTF-8') ?>">
      <?php if (!$user): ?>
        <section class="mg-embed-analytics-panel">
          <span class="mg-eyebrow">Merchant CRM</span>
          <h1>Saved Segment Action Center</h1>
          <p>Sign in to manage saved media CRM segments.</p>
          <a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a>
        </section>
      <?php else: ?>
        <section class="mg-embed-analytics-hero">
          <div>
            <span class="mg-eyebrow">Saved Segment Action Center v1</span>
            <h1 data-segment-title>Saved Segment Action Center</h1>
            <p data-segment-description>Preview matching contacts, monitor segment health, rename or duplicate the segment, and launch CRM actions.</p>
          </div>
          <form class="mg-embed-analytics-filters" data-segment-loader>
            <label>Segment ID
              <input name="segment" data-segment-input placeholder="saved segment id" value="<?= htmlspecialchars($selectedSegment, ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <div class="mg-embed-analytics-actions">
              <button class="mg-btn mg-btn-primary" type="submit">Load segment</button>
              <button class="mg-btn mg-btn-soft" type="button" data-segment-refresh>Refresh count</button>
              <a class="mg-btn mg-btn-ghost" href="/merchant-crm.php">CRM</a>
            </div>
          </form>
        </section>

        <section class="mg-embed-analytics-alert" data-segment-alert hidden></section>

        <section class="mg-embed-analytics-stats" data-segment-stats>
          <article><b>—</b><span>Current contacts</span></article>
          <article><b>—</b><span>Previous count</span></article>
          <article><b>—</b><span>Delta</span></article>
          <article><b>—</b><span>Window</span></article>
          <article><b>—</b><span>Health</span></article>
        </section>

        <section class="mg-embed-analytics-panel">
          <div class="mg-panel-head">
            <div><span class="mg-eyebrow">Action Center</span><h2>Launch segment actions</h2><p>Actions open the existing CRM bulk workflow with matching contacts selected.</p></div>
          </div>
          <div class="mg-embed-analytics-actions" data-segment-actions>
            <a class="mg-btn mg-btn-primary" href="#" data-segment-message>Message segment</a>
            <a class="mg-btn mg-btn-soft" href="#" data-segment-reward>Send reward</a>
            <a class="mg-btn mg-btn-soft" href="#" data-segment-followup>Create follow-up</a>
            <a class="mg-btn mg-btn-soft" href="#" data-segment-export>Export CSV</a>
            <a class="mg-btn mg-btn-ghost" href="#" data-segment-rules>Open rules</a>
          </div>
        </section>

        <section class="mg-embed-analytics-grid">
          <article class="mg-embed-analytics-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Rules</span><h2>Dynamic segment rules</h2></div></div>
            <div class="mg-embed-analytics-events" data-segment-rules-panel></div>
          </article>
          <article class="mg-embed-analytics-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Health</span><h2>Count movement</h2></div></div>
            <div class="mg-embed-analytics-events" data-segment-health></div>
          </article>
        </section>

        <section class="mg-embed-analytics-panel">
          <div class="mg-panel-head"><div><span class="mg-eyebrow">Manage</span><h2>Rename or duplicate</h2></div></div>
          <form class="mg-embed-analytics-filters" data-segment-manage-form>
            <label>Name
              <input data-segment-name placeholder="Segment name">
            </label>
            <label>Description
              <input data-segment-description-input placeholder="Internal description">
            </label>
            <div class="mg-embed-analytics-actions">
              <button class="mg-btn mg-btn-primary" type="submit">Rename</button>
              <button class="mg-btn mg-btn-soft" type="button" data-segment-duplicate>Duplicate</button>
              <button class="mg-btn mg-btn-ghost" type="button" data-segment-delete>Delete</button>
            </div>
          </form>
        </section>

        <section class="mg-embed-analytics-grid">
          <article class="mg-embed-analytics-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Preview</span><h2>Matching contacts</h2></div></div>
            <div class="mg-embed-analytics-table-wrap"><table class="mg-embed-analytics-table" data-segment-contact-table></table></div>
          </article>
          <article class="mg-embed-analytics-panel">
            <div class="mg-panel-head"><div><span class="mg-eyebrow">Activity</span><h2>Segment activity</h2></div></div>
            <div class="mg-embed-analytics-events" data-segment-activity></div>
          </article>
        </section>
      <?php endif; ?>
    </div>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
