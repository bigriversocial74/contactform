<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user = mg_require_admin_page_key('admin.pwa_branding');
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
        <div><a class="mg-pwa-branding-back" href="/admin/system-health.php">← System health</a><span class="mg-eyebrow">PWA brand control</span><h1>PWA icons, splash, and notification images</h1><p>Manage install icons, maskable launcher art, notification badge/icon assets, and the branded PWA splash page from the admin section.</p></div>
        <div class="mg-pwa-branding-hero-actions"><a class="mg-btn mg-btn-ghost" href="/manifest.php" target="_blank" rel="noopener">View manifest</a><a class="mg-btn mg-btn-soft" href="/pwa-splash.php" target="_blank" rel="noopener">Preview splash</a></div>
      </header>
      <section class="mg-pwa-branding-status is-loading" data-pwa-branding-status aria-live="polite"><span class="mg-pwa-branding-status-dot" aria-hidden="true"></span><div><strong>Loading PWA branding</strong><p>Checking schema, settings, and active images.</p></div></section>
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
