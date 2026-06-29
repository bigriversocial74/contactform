<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Merchant Branded App | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-pwa.css'];
$page_scripts = ['/assets/js/merchant-pwa-admin.js'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-merchant-pwa-admin" data-merchant-pwa-admin>
  <section class="mg-merchant-pwa-admin-shell">
    <header class="mg-merchant-pwa-admin-hero">
      <div>
        <a class="mg-btn mg-btn-ghost" href="/merchant.php">← Merchant dashboard</a>
        <span class="mg-eyebrow">Merchant-branded PWA</span>
        <h1>Your branded rewards app</h1>
        <p>Create a merchant-specific install screen, app icon, splash screen, manifest, shortcuts, and branded start URL — powered by Microgifter.</p>
      </div>
      <div class="mg-merchant-pwa-admin-actions">
        <a class="mg-btn mg-btn-soft" href="#" data-merchant-pwa-install-link target="_blank" rel="noopener">Preview install</a>
        <a class="mg-btn mg-btn-soft" href="#" data-merchant-pwa-app-link target="_blank" rel="noopener">Preview app</a>
        <a class="mg-btn mg-btn-ghost" href="#" data-merchant-pwa-manifest-link target="_blank" rel="noopener">Manifest</a>
      </div>
    </header>
    <div class="mg-merchant-pwa-status" data-merchant-pwa-status>Loading merchant app branding…</div>
    <div class="mg-merchant-pwa-admin-grid">
      <section class="mg-merchant-pwa-admin-panel">
        <header>
          <div>
            <h2>Install screen and app identity</h2>
            <p>These settings control the merchant URL, home-screen name, app colors, and first loaded branded experience.</p>
          </div>
          <button class="mg-btn mg-btn-primary" type="button" data-merchant-pwa-save>Save app branding</button>
        </header>
        <form class="mg-merchant-pwa-form" data-merchant-pwa-form>
          <label><span>Merchant slug</span><input name="merchant_slug" maxlength="100" placeholder="joes-coffee"></label>
          <label><span>Status</span><select name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option></select></label>
          <label><span>App name</span><input name="app_name" maxlength="100" placeholder="Joe’s Coffee Rewards"></label>
          <label><span>Short name</span><input name="short_name" maxlength="60" placeholder="Joe’s Coffee"></label>
          <label class="is-wide"><span>Description</span><textarea name="description" maxlength="280" rows="3"></textarea></label>
          <label><span>Install headline</span><input name="install_headline" maxlength="140"></label>
          <label><span>Splash title</span><input name="splash_title" maxlength="120"></label>
          <label class="is-wide"><span>Install subtitle</span><textarea name="install_subtitle" maxlength="320" rows="3"></textarea></label>
          <label class="is-wide"><span>Splash subtitle</span><textarea name="splash_subtitle" maxlength="320" rows="3"></textarea></label>
          <label><span>Theme color</span><input name="theme_color" type="color"></label>
          <label><span>Background color</span><input name="background_color" type="color"></label>
          <label><span>Install prompt</span><select name="enable_install_prompt"><option value="1">Enabled</option><option value="0">Disabled</option></select></label>
          <label><span>Push prompt</span><select name="enable_push_prompt"><option value="1">Enabled</option><option value="0">Disabled</option></select></label>
        </form>
      </section>
      <aside class="mg-merchant-pwa-preview">
        <div class="mg-merchant-pwa-preview-phone">
          <div class="mg-merchant-pwa-preview-screen">
            <img src="/images/logo_main_drk.png" alt="" data-merchant-pwa-preview-logo>
            <span class="mg-eyebrow">Home screen app</span>
            <strong data-merchant-pwa-preview-title>Merchant Rewards</strong>
            <p data-merchant-pwa-preview-subtitle>Rewards, gifts, claims, and offers powered by Microgifter.</p>
          </div>
        </div>
      </aside>
    </div>
    <section class="mg-merchant-pwa-admin-panel">
      <header>
        <div><h2>Merchant app images</h2><p>Upload public-safe PWA assets for the home-screen icon, splash screen, notification icon, and badge.</p></div>
      </header>
      <div class="mg-merchant-pwa-asset-grid" data-merchant-pwa-assets><p>Loading image slots…</p></div>
    </section>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
