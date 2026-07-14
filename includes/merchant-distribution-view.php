<?php
declare(strict_types=1);
$rdMerchantReadiness = [
  'configured' => false,
  'credential_found' => false,
  'app_live' => false,
  'key_live' => false,
  'scopes_ready' => false,
  'webhook_url_ready' => false,
  'webhook_secret_ready' => false,
  'program_id_ready' => false,
  'template_id_ready' => false,
  'schema_ready' => false,
  'app_name' => null,
];
try {
  require_once dirname(__DIR__) . '/games/reward-drop/includes/bootstrap.php';
  $rdMerchantReadiness = array_merge($rdMerchantReadiness, rd_readiness(mg_db(), rd_config()));
  $schemaStmt = mg_db()->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('reward_drop_runs','reward_drop_webhook_receipts')");
  $rdMerchantReadiness['schema_ready'] = (int)$schemaStmt->fetchColumn() === 2;
} catch (Throwable $rdError) {
  $rdMerchantReadiness['schema_ready'] = false;
}
$rdReady = $rdMerchantReadiness['configured']
  && $rdMerchantReadiness['credential_found']
  && $rdMerchantReadiness['app_live']
  && $rdMerchantReadiness['key_live']
  && $rdMerchantReadiness['scopes_ready']
  && $rdMerchantReadiness['webhook_url_ready']
  && $rdMerchantReadiness['webhook_secret_ready']
  && $rdMerchantReadiness['schema_ready'];
?>
<section class="mg-distribution-command" data-distribution-reach-center>
  <div class="mg-distribution-topbar">
    <nav class="mg-distribution-tabs" aria-label="Distribution sections">
      <a class="is-active" href="#distribution-overview" data-distribution-tab="overview" data-distribution-default="true">Overview</a>
      <a href="#distribution-programs" data-distribution-tab="overview">Channels</a>
      <a href="#distribution-programs" data-distribution-tab="overview">Programs</a>
      <a href="#distribution-game" data-distribution-tab="overview">Games</a>
      <a href="#distribution-signal" data-distribution-tab="overview">Partners</a>
      <a href="#distribution-signal" data-distribution-tab="overview">Events</a>
      <a href="#distribution-editor" data-distribution-tab="create">Create Distribution</a>
      <a href="#distribution-health" data-distribution-tab="overview">History</a>
    </nav>
    <button class="mg-btn mg-btn-primary" type="button" data-program-new data-distribution-open-create>Create Distribution</button>
  </div>

  <div class="mg-distribution-tab-section is-active" id="distribution-overview" data-distribution-section="overview">
    <div class="mg-distribution-kpis" data-distribution-kpis></div>

    <div class="mg-distribution-layout">
      <section class="mg-app-panel mg-distribution-panel" id="distribution-programs">
        <div class="mg-app-panel-head mg-distribution-panel-head">
          <div>
            <span class="mg-eyebrow">Distribution Network</span>
            <h2>Programs and channels</h2>
            <p>Operate contests, giveaways, workplace rewards, fundraising distributions, merchant grants, gaming inputs, partner channels, and API-driven programs.</p>
          </div>
          <div class="mg-heading-actions">
            <a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a>
            <a class="mg-btn mg-btn-soft" href="/merchant-campaign-stamps.php">Stamps</a>
          </div>
        </div>
        <div class="mg-app-panel-body">
          <div class="mg-distribution-toolbar"><input type="search" data-program-search placeholder="Search programs, channels, partners"><select data-program-status><option value="all">All statuses</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="archived">Archived</option></select></div>
          <div class="mg-program-list" data-program-list></div>
        </div>
      </section>

      <aside class="mg-distribution-side" id="distribution-signal">
        <section class="mg-app-panel mg-distribution-panel mg-distribution-signal-card">
          <div class="mg-app-panel-head mg-distribution-panel-head is-compact"><div><h2>Distribution Signal</h2><p>Network readiness and next best action.</p></div></div>
          <div class="mg-app-panel-body">
            <div class="mg-distribution-signal-visual" aria-hidden="true"><span></span><span></span><span></span><span></span><i></i></div>
            <div class="mg-distribution-signal-list">
              <p><b></b><span>Use active programs to turn campaign demand into issued rewards.</span></p>
              <p><b></b><span>Connect channels such as QR drops, events, partners, API apps, and workplace reward sources.</span></p>
              <p><b></b><span>Review source and issuance health before scaling distribution volume.</span></p>
            </div>
          </div>
        </section>

        <section class="mg-app-panel mg-distribution-panel mg-distribution-actions">
          <div class="mg-app-panel-head mg-distribution-panel-head is-compact"><div><h2>Quick actions</h2><p>Common distribution moves.</p></div></div>
          <div class="mg-app-panel-body">
            <a href="#distribution-editor" data-distribution-open-create>Add channel/program</a>
            <a href="/merchant-campaigns.php">Create QR drop</a>
            <a href="/merchant-campaigns.php">Connect campaign</a>
            <a href="/merchant-games.php">Manage Hosted Games</a>
            <a href="/merchant-campaign-stamps.php">Review stamps</a>
          </div>
        </section>
      </aside>
    </div>

    <section class="mg-app-panel mg-distribution-panel mg-game-integration <?= $rdReady ? 'is-ready' : 'needs-setup' ?>" id="distribution-game">
      <div class="mg-game-integration-hero">
        <div class="mg-game-integration-copy">
          <span class="mg-eyebrow">Legacy example game</span>
          <h2>Reward Drop</h2>
          <p>The original first-party browser game remains available as a working reference. New merchant games should be uploaded and configured through Hosted Games, which automatically provisions the player bridge, live API credential, signed webhook, campaign reward, release URL, and isolated game database.</p>
          <div class="mg-game-integration-actions">
            <a class="mg-btn mg-btn-primary" href="/merchant-games.php">Open Hosted Games</a>
            <a class="mg-btn mg-btn-soft" href="/games/reward-drop/" target="_blank" rel="noopener">Open legacy Reward Drop</a>
            <a class="mg-btn mg-btn-soft" href="#distribution-editor" data-distribution-open-create>Create gaming program</a>
          </div>
        </div>
        <div class="mg-game-integration-state"><strong><?= $rdReady ? 'Legacy game ready' : 'Legacy setup required' ?></strong><span><?= mg_e((string)($rdMerchantReadiness['app_name'] ?: 'Reward Drop developer app')) ?></span></div>
      </div>
      <div class="mg-game-readiness" aria-label="Legacy Reward Drop readiness">
        <?php
        $rdChecks = [
          ['Game SQL', $rdMerchantReadiness['schema_ready'], 'reward_drop_game_v1.sql'],
          ['Live app + key', $rdMerchantReadiness['app_live'] && $rdMerchantReadiness['key_live'], 'Developer app and credential'],
          ['API scopes', $rdMerchantReadiness['scopes_ready'], 'Issue and reward status'],
          ['Program + reward', $rdMerchantReadiness['program_id_ready'] && $rdMerchantReadiness['template_id_ready'], 'Active gaming distribution'],
          ['Signed webhook', $rdMerchantReadiness['webhook_url_ready'] && $rdMerchantReadiness['webhook_secret_ready'], '/games/reward-drop/webhook.php'],
        ];
        foreach ($rdChecks as $rdCheck): ?>
          <article class="<?= $rdCheck[1] ? 'is-ready' : 'needs-setup' ?>"><i></i><div><span><?= mg_e($rdCheck[0]) ?></span><strong><?= $rdCheck[1] ? 'Ready' : 'Required' ?></strong><small><?= mg_e($rdCheck[2]) ?></small></div></article>
        <?php endforeach; ?>
      </div>
      <div class="mg-game-flow"><span>Upload game ZIP</span><b>→</b><span>Connect campaign reward</span><b>→</b><span>Admin verifies game database</span><b>→</b><span>Publish game URL</span><b>→</b><span>User Inbox</span></div>
    </section>

    <section class="mg-app-panel mg-distribution-panel" id="distribution-health">
      <div class="mg-app-panel-head mg-distribution-panel-head"><div><span class="mg-eyebrow">Operations</span><h2>Source and issuance health</h2><p>External input connections and the current PPPM issuance queue.</p></div></div>
      <div class="mg-app-panel-body"><div class="mg-distribution-health"><div data-distribution-sources></div><div data-distribution-queue></div></div></div>
    </section>
  </div>

  <section class="mg-app-panel mg-distribution-panel mg-distribution-editor mg-distribution-tab-section" id="distribution-editor" data-distribution-section="create" aria-labelledby="distribution-editor-title" hidden>
    <div class="mg-app-panel-head mg-distribution-panel-head">
      <div><span class="mg-eyebrow">Builder</span><h2 id="distribution-editor-title">Create Distribution</h2><p>Define campaign type, products, dates, capacity, budget, and recipient limits in a dedicated distribution builder.</p></div>
      <a class="mg-btn mg-btn-soft" href="#distribution-overview" data-distribution-tab="overview">Back to programs</a>
    </div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form mg-distribution-form" data-program-form>
        <input type="hidden" name="program_id">
        <label>Name<input name="name" required maxlength="180"></label>
        <div class="mg-grid-2"><label>Type<select name="program_type"><option value="merchant_grant">Merchant grant</option><option value="contest">Contest</option><option value="giveaway">Giveaway</option><option value="fundraiser">Fundraiser</option><option value="workplace_reward">Workplace reward</option><option value="gaming">Gaming</option><option value="external_api">External API</option><option value="batch">Batch</option><option value="purchase">Purchase</option><option value="other">Other</option></select></label><label>Status<select name="status"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option></select></label></div>
        <div class="mg-program-product-field"><div class="mg-program-product-field-head"><span>Products included</span><small>Choose one or more published products that this distribution program can issue.</small></div><div class="mg-program-product-picker" data-program-product-picker><p>Loading available products…</p></div></div>
        <div class="mg-grid-2"><label>Starts at<input name="starts_at" type="datetime-local"></label><label>Ends at<input name="ends_at" type="datetime-local"></label></div>
        <div class="mg-grid-2"><label>Budget, cents<input name="budget_cents" type="number" min="0"></label><label>Maximum items<input name="max_items" type="number" min="1"></label></div>
        <label>Per-recipient limit<input name="per_recipient_limit" type="number" min="1"></label>
        <div class="mg-form-status" data-program-status-message></div>
        <button class="mg-btn mg-btn-primary" type="submit">Save program</button>
      </form>
    </div>
  </section>
</section>
