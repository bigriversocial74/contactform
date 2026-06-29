<section class="mg-merchant-pwa-admin" data-merchant-pwa-admin>
  <section class="mg-merchant-pwa-admin-shell">
    <header class="mg-merchant-pwa-admin-hero">
      <div>
        <a class="mg-btn mg-btn-ghost" href="/merchant.php">← Merchant dashboard</a>
        <span class="mg-eyebrow">Merchant-branded PWA</span>
        <h1>Design, review, and grow your rewards app</h1>
        <p>Create the merchant install experience, review launch readiness, copy the customer install link, and track early PWA adoption signals from one dashboard.</p>
      </div>
      <div class="mg-merchant-pwa-admin-actions">
        <button class="mg-btn mg-btn-primary" type="button" data-merchant-pwa-copy>Copy install link</button>
        <a class="mg-btn mg-btn-soft" href="#" data-merchant-pwa-install-link target="_blank" rel="noopener">Preview install</a>
        <a class="mg-btn mg-btn-soft" href="#" data-merchant-pwa-app-link target="_blank" rel="noopener">Preview app</a>
        <a class="mg-btn mg-btn-ghost" href="#" data-merchant-pwa-manifest-link target="_blank" rel="noopener">Manifest</a>
      </div>
    </header>

    <div class="mg-merchant-pwa-status" data-merchant-pwa-status>Loading merchant app dashboard…</div>

    <section class="mg-merchant-pwa-kpis" data-merchant-pwa-kpis>
      <article><span>Launch score</span><strong data-pwa-launch-score>0%</strong><small>Required setup complete</small></article>
      <article><span>Install page views</span><strong data-pwa-views>0</strong><small>Analytics-ready placeholder</small></article>
      <article><span>Installs</span><strong data-pwa-installs>0</strong><small>Browser prompt acceptance</small></article>
      <article><span>App opens</span><strong data-pwa-opens>0</strong><small>Home-screen launch events</small></article>
    </section>

    <section class="mg-merchant-pwa-dashboard-grid">
      <div class="mg-merchant-pwa-dashboard-main">
        <section class="mg-merchant-pwa-admin-panel mg-merchant-pwa-panel-design">
          <header>
            <div>
              <span class="mg-eyebrow">Design</span>
              <h2>Install screen and app identity</h2>
              <p>These settings control the merchant URL, home-screen name, app colors, install copy, and first loaded branded experience.</p>
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

        <section class="mg-merchant-pwa-admin-panel mg-merchant-pwa-panel-review">
          <header>
            <div>
              <span class="mg-eyebrow">Review</span>
              <h2>Launch readiness checklist</h2>
              <p>Confirm the merchant app can be shared before adding the install link to receipts, QR table tents, campaigns, or reward landing pages.</p>
            </div>
          </header>
          <div class="mg-merchant-pwa-review-grid" data-merchant-pwa-review>
            <article data-check="slug"><strong>Merchant slug</strong><span>Waiting for profile…</span></article>
            <article data-check="status"><strong>Status selected</strong><span>Waiting for profile…</span></article>
            <article data-check="app_icon_192"><strong>192 icon</strong><span>Missing</span></article>
            <article data-check="app_icon_512"><strong>512 icon</strong><span>Missing</span></article>
            <article data-check="splash_logo"><strong>Splash logo</strong><span>Missing</span></article>
            <article data-check="manifest"><strong>Manifest</strong><span>Waiting for URL…</span></article>
            <article data-check="install"><strong>Install page</strong><span>Waiting for URL…</span></article>
            <article data-check="app"><strong>App start page</strong><span>Waiting for URL…</span></article>
          </div>
        </section>

        <section class="mg-merchant-pwa-admin-panel">
          <header>
            <div>
              <span class="mg-eyebrow">Assets</span>
              <h2>Merchant app images</h2>
              <p>Upload public-safe PWA assets for the home-screen icon, splash screen, notification icon, badge, and branded background.</p>
            </div>
          </header>
          <div class="mg-merchant-pwa-asset-grid" data-merchant-pwa-assets><p>Loading image slots…</p></div>
        </section>
      </div>

      <aside class="mg-merchant-pwa-dashboard-side">
        <section class="mg-merchant-pwa-preview">
          <header><span class="mg-eyebrow">Live preview</span><strong data-merchant-pwa-preview-title>Merchant Rewards</strong></header>
          <div class="mg-merchant-pwa-preview-phone">
            <div class="mg-merchant-pwa-preview-screen">
              <img src="/images/logo_main_drk.png" alt="" data-merchant-pwa-preview-logo>
              <span class="mg-eyebrow">Home screen app</span>
              <strong data-merchant-pwa-preview-phone-title>Merchant Rewards</strong>
              <p data-merchant-pwa-preview-subtitle>Rewards, gifts, claims, and offers powered by Microgifter.</p>
              <div class="mg-merchant-pwa-preview-actions"><i></i><i></i><i></i></div>
            </div>
          </div>
        </section>

        <section class="mg-merchant-pwa-admin-panel mg-merchant-pwa-share-panel">
          <header>
            <div><span class="mg-eyebrow">Share</span><h2>Install link</h2><p>Use this link or QR-ready URL in campaign materials.</p></div>
          </header>
          <div class="mg-merchant-pwa-linkbox" data-merchant-pwa-linkbox>/m/merchant/</div>
          <div class="mg-merchant-pwa-qr-card">
            <div class="mg-merchant-pwa-fake-qr" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
            <p data-merchant-pwa-qr-text>Save the install URL, then generate QR creative for receipts, table tents, emails, and social posts.</p>
          </div>
        </section>

        <section class="mg-merchant-pwa-admin-panel mg-merchant-pwa-analytics-panel">
          <header><div><span class="mg-eyebrow">Analytics</span><h2>Adoption signals</h2><p>Event tracking hooks are ready for install views, prompt accepts, app opens, and notification opt-ins.</p></div></header>
          <div class="mg-merchant-pwa-analytics-list" data-merchant-pwa-analytics>
            <article><strong>0</strong><span>Install page views</span></article>
            <article><strong>0</strong><span>Install prompt accepted</span></article>
            <article><strong>0</strong><span>App opens from icon</span></article>
            <article><strong>0</strong><span>Notification opt-ins</span></article>
          </div>
        </section>
      </aside>
    </section>
  </section>
</section>
