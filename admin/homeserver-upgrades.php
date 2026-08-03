<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user = mg_require_admin_page_any(['admin.settings.manage']);
$page_title = 'HomeServer Updates | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-homeserver-releases-page';
$page_styles = ['/assets/css/admin-shell.css', '/assets/css/admin-homeserver-releases.css'];
$page_scripts = ['/assets/js/admin-homeserver-upgrades.js'];
$adminActive = 'homeserver-releases';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-hsr-shell" data-homeserver-upgrade-admin>
      <header class="mg-hsr-hero">
        <div>
          <a class="mg-hsr-back" href="/admin/homeserver-releases.php">← Installer releases</a>
          <span class="mg-eyebrow">Microgifter primary authority</span>
          <h1>HomeServer signed update control</h1>
          <p>Activate signed manifests, stage rollout, pause delivery, revoke a release, select a rollback target, and review HomeServer installation receipts.</p>
        </div>
        <div class="mg-hsr-hero-actions">
          <a class="mg-btn mg-btn-soft" href="/api/homeserver/update-manifest-stable.php" target="_blank" rel="noopener">View live manifest</a>
          <button class="mg-btn mg-btn-soft" type="button" data-hsu-refresh>Refresh</button>
        </div>
      </header>

      <section class="mg-hsr-status is-loading" data-hsu-status aria-live="polite">
        <span class="mg-hsr-status-dot" aria-hidden="true"></span>
        <div><strong>Loading update authority</strong><p>Checking release signing, active rollout, rollback readiness, and update receipts.</p></div>
      </section>

      <section class="mg-hsr-stats" aria-label="HomeServer update statistics">
        <article><span>Configured</span><strong data-hsu-stat="configured">0</strong><small>Signed release controls</small></article>
        <article><span>Active</span><strong data-hsu-stat="active">0</strong><small>Eligible manifests</small></article>
        <article><span>Installed</span><strong data-hsu-stat="succeeded">0</strong><small>Successful receipts</small></article>
        <article><span>Rollbacks</span><strong data-hsu-stat="rolled_back">0</strong><small>Rollback receipts</small></article>
      </section>

      <div class="mg-hsr-layout">
        <section class="mg-hsr-panel mg-hsr-upload-panel">
          <header><div><span>Signed manifest</span><h2>Configure release control</h2><p>Create the exact manifest payload outside the site, sign it with the offline Ed25519 release key, then paste the signature here. The private key never enters Microgifter.</p></div></header>
          <form class="mg-hsr-upload-form" data-hsu-form>
            <label><span>Installer release</span><select name="release_id" required data-hsu-release-select><option value="">Choose a release</option></select></label>
            <div class="mg-hsr-field-row">
              <label><span>Update class</span><select name="update_class"><option value="feature">Feature</option><option value="maintenance">Maintenance</option><option value="security">Security</option><option value="recovery">Recovery</option><option value="bootstrap">Bootstrap</option><option value="preview">Preview</option></select></label>
              <label><span>Rollout</span><input name="rollout_percentage" type="number" min="0" max="100" value="100" required><small>0–100 percent.</small></label>
            </div>
            <label><span>Authenticode thumbprint</span><input name="authenticode_thumbprint" maxlength="64" required autocomplete="off" placeholder="40- or 64-character certificate thumbprint"></label>
            <label><span>Manifest key ID</span><input name="manifest_key_id" maxlength="120" value="homeserver-release-2026-01" required autocomplete="off"></label>
            <label><span>Ed25519 manifest signature</span><textarea name="manifest_signature" rows="4" maxlength="160" required placeholder="Base64url signature without padding"></textarea></label>
            <label><span>Rollback release</span><select name="rollback_release_id" data-hsu-rollback-select><option value="">No rollback target</option></select></label>
            <div class="mg-hsr-checks">
              <label><input type="checkbox" name="activate"><span><strong>Activate immediately</strong><small>Publishes this signed manifest to eligible HomeServers after validation.</small></span></label>
            </div>
            <button class="mg-btn mg-btn-primary" type="submit" data-hsu-submit>Verify and save control</button>
          </form>
        </section>

        <aside class="mg-hsr-panel mg-hsr-readiness-panel">
          <header><div><span>Trust readiness</span><h2>Update signing boundary</h2></div></header>
          <div class="mg-hsr-readiness" data-hsu-readiness>
            <article><span>Upgrade migration</span><strong>Checking…</strong><p>Signed controls and immutable events.</p></article>
            <article><span>Public verification key</span><strong>Checking…</strong><p>Configured outside the repository and database.</p></article>
            <article><span>Manifest endpoint</span><strong>Checking…</strong><p data-hsu-manifest-url>Stable updater contract.</p></article>
            <article><span>Private key</span><strong>Offline only</strong><p>Never stored by Microgifter.</p></article>
          </div>
          <div class="mg-hsr-foundation-note"><strong>Existing updater retained</strong><p>HomeServer continues to verify Ed25519, SHA-256, Authenticode, pre-update backup, exact-version health, and automatic rollback. This page controls distribution; it does not create a second updater.</p></div>
        </aside>
      </div>

      <section class="mg-hsr-panel">
        <header class="mg-hsr-panel-head"><div><span>Release policy</span><h2>Upgrade controls</h2><p>Paused and revoked releases cannot be returned by the public manifest endpoint. Rollback activates the selected prior signed release.</p></div></header>
        <div class="mg-hsr-table-wrap"><table class="mg-hsr-table"><thead><tr><th>Version</th><th>Trust</th><th>Rollout</th><th>State</th><th>Rollback</th><th>Actions</th></tr></thead><tbody data-hsu-control-rows><tr><td colspan="6" class="mg-hsr-empty">Loading controls…</td></tr></tbody></table></div>
      </section>

      <section class="mg-hsr-panel">
        <header class="mg-hsr-panel-head"><div><span>Authority ledger</span><h2>Recent update-control events</h2><p>Configuration, activation, pause, rollout, revocation, and rollback changes remain auditable.</p></div></header>
        <div class="mg-hsr-table-wrap"><table class="mg-hsr-table"><thead><tr><th>Time</th><th>Version</th><th>Event</th><th>State</th><th>Operator</th></tr></thead><tbody data-hsu-event-rows><tr><td colspan="5" class="mg-hsr-empty">Loading events…</td></tr></tbody></table></div>
      </section>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
