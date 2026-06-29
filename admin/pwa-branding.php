<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user = mg_require_admin_page_any(['admin.pwa_branding.view','admin.pwa_branding.manage','admin.settings.manage']);
$page_title = 'PWA Branding | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-pwa-branding-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-pwa-branding.css'];
$page_scripts = ['/assets/js/admin-pwa-branding.js'];
$adminActive = 'pwa-branding';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-pwa-branding-shell" data-pwa-branding-admin>
      <header class="mg-pwa-branding-hero">
        <div><a class="mg-pwa-branding-back" href="/admin/system-health.php">← System health</a><span class="mg-eyebrow">PWA brand control</span><h1>PWA icons, splash, and notification images</h1><p>Manage install icons, maskable launcher art, notification badge/icon assets, the branded splash page, and VAPID setup helpers from the admin section.</p></div>
        <div class="mg-pwa-branding-hero-actions"><a class="mg-btn mg-btn-ghost" href="/manifest.php" target="_blank" rel="noopener">View manifest</a><a class="mg-btn mg-btn-soft" href="/pwa-splash.php" target="_blank" rel="noopener">Preview splash</a></div>
      </header>
      <section class="mg-pwa-branding-status is-loading" data-pwa-branding-status aria-live="polite"><span class="mg-pwa-branding-status-dot" aria-hidden="true"></span><div><strong>Loading PWA branding</strong><p>Checking schema, settings, active images, and push config.</p></div></section>
      <section class="mg-pwa-branding-panel mg-pwa-vapid-panel" data-pwa-vapid-panel>
        <header>
          <div>
            <h2>Push notification setup</h2>
            <p>Browser push requires VAPID keys. Generate a key pair here, then copy the values into your private server environment config. The private key is never saved by this admin page.</p>
          </div>
          <button class="mg-btn mg-btn-primary" type="button" data-pwa-generate-vapid>Generate VAPID keys</button>
        </header>
        <div class="mg-pwa-vapid-grid">
          <div class="mg-pwa-vapid-card"><span>PWA push</span><strong data-pwa-vapid-enabled>Checking…</strong></div>
          <div class="mg-pwa-vapid-card"><span>Public key</span><strong data-pwa-vapid-public>Checking…</strong></div>
          <div class="mg-pwa-vapid-card"><span>Private key</span><strong data-pwa-vapid-private>Checking…</strong></div>
          <div class="mg-pwa-vapid-card"><span>WebPush library</span><strong data-pwa-vapid-provider>Checking…</strong></div>
        </div>
        <div class="mg-pwa-vapid-help">
          <strong>Can this be bypassed?</strong>
          <p>Only if you do not use real browser push. You can keep in-app notifications, but installed PWA/browser notifications require VAPID keys.</p>
        </div>
        <div class="mg-pwa-vapid-output" data-pwa-vapid-output hidden>
          <div><strong>Generated keys — copy now</strong><p data-pwa-vapid-warning>The private key is shown one time and should only be placed in server config.</p></div>
          <textarea readonly spellcheck="false" data-pwa-vapid-env></textarea>
          <button class="mg-btn mg-btn-soft" type="button" data-pwa-copy-vapid>Copy env block</button>
        </div>
      </section>
      <div class="mg-pwa-branding-layout">
        <section class="mg-pwa-branding-panel mg-pwa-branding-settings">
          <header><div><h2>App and splash copy</h2><p>These settings drive the dynamic manifest and launch screen.</p></div><button class="mg-btn mg-btn-primary" type="button" data-pwa-save>Save settings</button></header>
          <form class="mg-pwa-branding-form" data-pwa-settings-form>
            <label><span>App name</span><input name="app_name" maxlength="80" autocomplete="off"></label>
            <label><span>Short name</span><input name="short_name" maxlength="80" autocomplete="off"></label>
            <label class="is-wide"><span>Description</span><textarea name="description" maxlength="260" rows="3"></textarea></label>
            <label><span>Start URL</span><input name="start_url" maxlength="500" placeholder="/pwa-splash.php"></label>
            <label><span>Scope</span><input name="scope" maxlength="500" placeholder="/"></label>
            <label><span>Display</span><select name="display"><option value="standalone">standalone</option><option value="fullscreen">fullscreen</option><option value="minimal-ui">minimal-ui</option><option value="browser">browser</option></select></label>
            <label><span>Theme color</span><input name="theme_color" type="color"></label>
            <label><span>Background color</span><input name="background_color" type="color"></label>
            <label><span>Splash title</span><input name="splash_title" maxlength="80"></label>
            <label><span>Splash CTA label</span><input name="splash_cta_label" maxlength="80"></label>
            <label><span>Splash CTA URL</span><input name="splash_cta_url" maxlength="500" placeholder="/notifications.php"></label>
            <label class="is-wide"><span>Splash subtitle</span><textarea name="splash_subtitle" maxlength="260" rows="3"></textarea></label>
          </form>
        </section>
        <aside class="mg-pwa-branding-preview" data-pwa-preview><div class="mg-pwa-phone"><div class="mg-pwa-phone-screen"><img data-pwa-preview-logo src="/images/logo_main_drk.png" alt=""><span>Installable workspace</span><strong data-pwa-preview-title>Microgifter</strong><p data-pwa-preview-subtitle>Gifts, rewards, campaigns, claims, and merchant alerts.</p><button type="button" data-pwa-preview-cta>Open notifications</button></div></div><div class="mg-pwa-preview-meta"><strong data-pwa-preview-name>Microgifter</strong><span data-pwa-preview-url>/manifest.php</span></div></aside>
      </div>
      <section class="mg-pwa-branding-panel"><header><div><h2>Image upload slots</h2><p>Upload public-safe PWA assets. The active image for each slot automatically replaces the previous one.</p></div></header><div class="mg-pwa-asset-grid" data-pwa-asset-grid><p class="mg-muted">Loading upload slots…</p></div></section>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
