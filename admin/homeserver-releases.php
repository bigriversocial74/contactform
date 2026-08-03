<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user = mg_require_admin_page_any(['admin.settings.manage']);
$page_title = 'HomeServer Releases | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-homeserver-releases-page';
$page_styles = ['/assets/css/admin-shell.css', '/assets/css/admin-homeserver-releases.css'];
$page_scripts = ['/assets/js/admin-homeserver-releases.js'];
$adminActive = 'homeserver-releases';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-hsr-shell" data-homeserver-release-admin>
      <header class="mg-hsr-hero">
        <div>
          <a class="mg-hsr-back" href="/account-homeserver.php">← HomeServer settings</a>
          <span class="mg-eyebrow">Windows distribution control</span>
          <h1>HomeServer releases and downloads</h1>
          <p>Upload the current Windows installer, publish the latest version, and review authenticated download activity. The signed update control center publishes these verified installers to the active HomeServer updater.</p>
        </div>
        <div class="mg-hsr-hero-actions">
          <a class="mg-btn mg-btn-primary" href="/admin/homeserver-upgrades.php">Signed update control</a>
          <a class="mg-btn mg-btn-soft" href="/api/homeserver/latest-release.php" target="_blank" rel="noopener">View latest metadata</a>
          <a class="mg-btn mg-btn-soft" href="/account-homeserver.php">User download view</a>
        </div>
      </header>

      <section class="mg-hsr-status is-loading" data-hsr-status aria-live="polite">
        <span class="mg-hsr-status-dot" aria-hidden="true"></span>
        <div><strong>Loading HomeServer releases</strong><p>Checking the release schema, protected storage, latest version, and download history.</p></div>
      </section>

      <section class="mg-hsr-stats" aria-label="HomeServer release statistics">
        <article><span>Releases</span><strong data-hsr-stat="release_count">0</strong><small>All uploaded versions</small></article>
        <article><span>Published</span><strong data-hsr-stat="published_count">0</strong><small>Available versions</small></article>
        <article><span>Downloads</span><strong data-hsr-stat="download_count">0</strong><small>Authenticated requests</small></article>
        <article><span>Latest stable</span><strong data-hsr-stat="latest_version">—</strong><small>Windows x64</small></article>
      </section>

      <div class="mg-hsr-layout">
        <section class="mg-hsr-panel mg-hsr-upload-panel">
          <header><div><span>Release publisher</span><h2>Upload HomeServer installer</h2><p>The executable is verified by extension, Windows PE signature, MIME type, size, and SHA-256 checksum before it is stored outside the web root.</p></div></header>
          <form class="mg-hsr-upload-form" data-hsr-upload-form enctype="multipart/form-data">
            <label><span>Version</span><input name="version" required maxlength="64" placeholder="1.0.0" autocomplete="off"><small>Semantic version, such as 1.0.0 or 1.0.0-beta.1.</small></label>
            <div class="mg-hsr-field-row">
              <label><span>Channel</span><select name="channel"><option value="stable">Stable</option><option value="beta">Beta</option><option value="preview">Preview</option></select></label>
              <label><span>Architecture</span><select name="architecture"><option value="x64">Windows x64</option><option value="arm64">Windows ARM64</option></select></label>
            </div>
            <label><span>Minimum supported version</span><input name="minimum_supported_version" maxlength="64" placeholder="Optional" autocomplete="off"><small>Enforced by the signed HomeServer update manifest.</small></label>
            <label><span>Release notes</span><textarea name="release_notes" maxlength="12000" rows="6" placeholder="What changed in this HomeServer release?"></textarea></label>
            <label class="mg-hsr-file-field"><span>Windows installer</span><input name="file" type="file" required accept=".exe,application/vnd.microsoft.portable-executable,application/x-msdownload"><strong data-hsr-file-name>Choose the latest .exe file</strong><small data-hsr-file-limit>Maximum application limit: 1 GB. The web server may impose a smaller PHP upload limit.</small></label>
            <div class="mg-hsr-checks">
              <label><input type="checkbox" name="publish_now" checked><span><strong>Publish as latest</strong><small>Immediately replaces the current latest version for this channel and architecture.</small></span></label>
              <label><input type="checkbox" name="mandatory_update"><span><strong>Mandatory update flag</strong><small>Included in release policy while installation remains user-authorized and rollback protected.</small></span></label>
            </div>
            <button class="mg-btn mg-btn-primary mg-hsr-upload-button" type="submit" data-hsr-upload>Upload and publish</button>
          </form>
        </section>

        <aside class="mg-hsr-panel mg-hsr-readiness-panel">
          <header><div><span>Distribution readiness</span><h2>Protected storage</h2></div></header>
          <div class="mg-hsr-readiness" data-hsr-readiness>
            <article><span>Database migration</span><strong>Checking…</strong><p>Release and download tracking tables.</p></article>
            <article><span>Persistent storage</span><strong>Checking…</strong><p>Installer files remain outside the web root.</p></article>
            <article><span>Download access</span><strong>Authenticated</strong><p>Only signed-in Microgifter accounts can request the installer.</p></article>
            <article><span>Integrity metadata</span><strong>SHA-256</strong><p>Every uploaded installer receives an immutable checksum.</p></article>
          </div>
          <div class="mg-hsr-foundation-note"><strong>Active updater catalog</strong><p>Version, channel, architecture, checksum, minimum version, signing identity, rollout, revocation, and rollback are joined by the signed update control center.</p></div>
        </aside>
      </div>

      <section class="mg-hsr-panel">
        <header class="mg-hsr-panel-head"><div><span>Version control</span><h2>HomeServer release history</h2><p>Only one release can be latest for each channel and architecture. Retired installers remain in the audit history but cannot be downloaded.</p></div><button class="mg-btn mg-btn-soft" type="button" data-hsr-refresh>Refresh</button></header>
        <div class="mg-hsr-table-wrap"><table class="mg-hsr-table"><thead><tr><th>Version</th><th>Release</th><th>File</th><th>Downloads</th><th>Published</th><th>Actions</th></tr></thead><tbody data-hsr-release-rows><tr><td colspan="6" class="mg-hsr-empty">Loading releases…</td></tr></tbody></table></div>
      </section>

      <section class="mg-hsr-panel">
        <header class="mg-hsr-panel-head"><div><span>Request ledger</span><h2>Recent installer downloads</h2><p>These records represent authenticated download requests. They do not claim that the browser completed installation.</p></div></header>
        <div class="mg-hsr-table-wrap"><table class="mg-hsr-table mg-hsr-download-table"><thead><tr><th>Time</th><th>User</th><th>Version</th><th>Channel</th><th>Client</th></tr></thead><tbody data-hsr-download-rows><tr><td colspan="5" class="mg-hsr-empty">Loading download activity…</td></tr></tbody></table></div>
      </section>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
