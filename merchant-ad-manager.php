<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/api/ads/_ads.php';

$user = mg_require_auth('/signin.php', '/merchant-ad-manager.php');
$pdo = mg_db();
$page_title = 'Campaign Ads Manager | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'ads-manager';
$page_body_class = 'mg-ad-manager-page mg-ad-manager-redesign-page';
$page_styles = ['/assets/css/merchant-ad-manager.css','/assets/css/sponsored-campaign-card.css','/assets/css/ad-health-alerts.css','/assets/css/merchant-ad-product-picker.css'];
$page_scripts = ['/assets/js/sponsored-campaign-card.js','/assets/js/ad-health-alerts.js','/assets/js/merchant-ad-manager.js','/assets/js/merchant-ad-lifecycle-guard.js'];
$page_manifest = [
    'id' => 'merchant-ad-manager',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'merchant-ad-manager', 'sections' => []],
];
$merchantName = trim((string)($user['display_name'] ?? $user['full_name'] ?? 'Microgifter Merchant')) ?: 'Microgifter Merchant';
$csrfToken = mg_csrf_token();
$schema = mg_ads_schema_status($pdo);

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-app">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <div class="mg-app-workspace">
    <main class="mg-ads-shell mg-ads-redesign" data-ads-manager data-csrf-token="<?php echo mg_e($csrfToken); ?>" data-merchant-name="<?php echo mg_e($merchantName); ?>">
      <header class="mg-ads-page-head">
        <div>
          <h1>Campaign Ads <span aria-hidden="true">ⓘ</span></h1>
          <p>Create and manage campaign boosts and sponsored local drops.</p>
        </div>
        <a class="mg-btn mg-btn-primary" href="#create" data-ads-tab-jump="create">+ New Campaign</a>
      </header>

      <?php if (!mg_ads_user_can_merchant($user, $pdo)): ?>
        <section class="mg-ads-panel"><div class="mg-ads-alert">Merchant access is required to use Campaign Ads Manager.</div></section>
      <?php else: ?>
        <?php if (!$schema['ready']): ?>
          <section class="mg-ads-panel"><div class="mg-ads-alert">SQL migration required: run <strong>database/microgifter_ads_manager_phase1.sql</strong> before saving ad campaigns.</div></section>
        <?php endif; ?>

        <section class="mg-ad-health-alerts" data-ad-health-alerts data-health-scope="merchant" aria-live="polite"></section>

        <section class="mg-ads-kpi-strip" aria-label="Advertising performance summary">
          <article class="mg-ads-kpi-card"><span class="mg-ads-kpi-icon">◉</span><div><span>Impressions</span><strong data-kpi="impressions">0</strong><small>Live campaign reach</small></div><i aria-hidden="true"></i></article>
          <article class="mg-ads-kpi-card"><span class="mg-ads-kpi-icon">↗</span><div><span>Clicks</span><strong data-kpi="clicks">0</strong><small>Traffic to offers</small></div><i aria-hidden="true"></i></article>
          <article class="mg-ads-kpi-card"><span class="mg-ads-kpi-icon">□</span><div><span>Claims</span><strong data-kpi="claims">0</strong><small>Wallet and claim actions</small></div><i aria-hidden="true"></i></article>
          <article class="mg-ads-kpi-card"><span class="mg-ads-kpi-icon">▣</span><div><span>Redemptions</span><strong data-kpi="redemptions">0</strong><small>Measured commerce</small></div><i aria-hidden="true"></i></article>
        </section>

        <nav class="mg-ads-tabbar" aria-label="Campaign Ads sections">
          <button class="is-active" type="button" data-ads-tab-button="create">Create Campaign</button>
          <button type="button" data-ads-tab-button="preview">Sponsored Preview</button>
          <button type="button" data-ads-tab-button="campaigns">Merchant Campaigns</button>
          <button type="button" data-ads-tab-button="analytics">Analytics</button>
        </nav>

        <section class="mg-ads-tab-panel is-active" data-ads-tab-panel="create" id="create">
          <div class="mg-ads-create-layout">
            <article class="mg-ads-panel mg-ads-create-panel">
              <h2>Create Campaign Boost / Sponsored Local Drop</h2>
              <form class="mg-ads-form" data-ad-form onsubmit="return false;">
                <div class="mg-ads-product-picker">
                  <div class="mg-ads-field">
                    <label for="ad-product">Choose product / reward to advertise</label>
                    <select id="ad-product" name="source_product_id" data-product-picker>
                      <option value="">Loading merchant products…</option>
                    </select>
                    <small class="mg-ads-field-hint">Optional. Applying a product prefills the headline, offer copy, CTA, destination, and product metadata.</small>
                  </div>
                  <button class="mg-btn mg-btn-soft" type="button" data-apply-product disabled>Apply Product</button>
                </div>
                <div class="mg-ads-product-summary" data-product-summary hidden></div>
                <div class="mg-ads-field"><label for="ad-title">Campaign title</label><input id="ad-title" name="title" maxlength="190" value="Sponsored Local Drop" required></div>
                <div class="mg-ads-field"><label for="ad-headline">Sponsored card headline</label><input id="ad-headline" name="headline" maxlength="190" value="Featured Local Reward" required></div>
                <div class="mg-ads-field"><label for="ad-description">Short offer description</label><textarea id="ad-description" name="description" maxlength="2000" placeholder="Describe the reward, campaign, or local opportunity.">Claim this local reward, save it to your wallet, and redeem it with the merchant.</textarea></div>
                <div class="mg-ads-two">
                  <div class="mg-ads-field"><label for="ad-objective">Objective</label><select id="ad-objective" name="objective"><option value="claim_growth">Claim Growth</option><option value="redemption_growth">Redemption Growth</option><option value="gift_sales">Gift Sales</option><option value="loyalty_growth">Loyalty Growth</option><option value="referral_growth">Referral Growth</option><option value="event_traffic">Event Traffic</option><option value="reengagement">Re-Engagement</option><option value="local_awareness">Local Awareness</option><option value="local_drop">Local Drop</option><option value="target_zone_activation">Target Zone Activation</option></select></div>
                  <div class="mg-ads-field"><label for="ad-budget-type">Budget / limit type</label><select id="ad-budget-type" name="budget_type"><option value="none">No billing in Phase 1</option><option value="flat_boost">Flat Boost</option><option value="claim_cap">Claim Cap</option><option value="redemption_cap">Redemption Cap</option><option value="sponsored_reward_budget">Sponsored Reward Budget</option></select></div>
                </div>
                <div class="mg-ads-two">
                  <div class="mg-ads-field"><label for="ad-budget">Budget amount</label><input id="ad-budget" name="budget_amount" type="number" min="0" step="0.01" placeholder="0.00"></div>
                  <div class="mg-ads-field"><label for="ad-claim-cap">Claim cap</label><input id="ad-claim-cap" name="claim_cap" type="number" min="0" step="1" placeholder="250"></div>
                </div>
                <div class="mg-ads-two">
                  <div class="mg-ads-field"><label for="ad-redemption-cap">Redemption cap</label><input id="ad-redemption-cap" name="redemption_cap" type="number" min="0" step="1"></div>
                  <div class="mg-ads-field"><label for="ad-zone">Target Zone ID</label><input id="ad-zone" name="target_zone_id" inputmode="numeric" placeholder="Optional existing zone id"></div>
                </div>
                <div class="mg-ads-two">
                  <div class="mg-ads-field"><label for="ad-start">Start</label><input id="ad-start" name="starts_at" type="datetime-local"></div>
                  <div class="mg-ads-field"><label for="ad-end">End</label><input id="ad-end" name="ends_at" type="datetime-local"></div>
                </div>
                <div class="mg-ads-creative-upload">
                  <div class="mg-ads-creative-upload-copy">
                    <strong>Campaign image</strong>
                    <span data-image-source-label>Manual URL fallback</span>
                  </div>
                  <div class="mg-ads-field">
                    <label for="ad-image-file">Upload campaign image</label>
                    <input id="ad-image-file" type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-creative-image-file>
                    <small class="mg-ads-field-hint">JPG, PNG, GIF, or WebP up to 8MB. Uploads override product images while keeping the Image URL field editable.</small>
                  </div>
                  <button class="mg-btn mg-btn-soft" type="button" data-upload-creative>Upload Image</button>
                  <small data-creative-upload-status aria-live="polite"></small>
                </div>
                <div class="mg-ads-field"><label for="ad-image">Image URL</label><input id="ad-image" name="image_url" placeholder="/images/example-offer.png"><small class="mg-ads-field-hint">Use this as a fallback, or paste an existing hosted campaign image.</small></div>
                <div class="mg-ads-two">
                  <div class="mg-ads-field"><label for="ad-cta">CTA label</label><input id="ad-cta" name="cta_label" maxlength="80" value="Claim Reward"></div>
                  <div class="mg-ads-field"><label for="ad-destination">Destination URL</label><input id="ad-destination" name="destination_url" placeholder="/feed.php"></div>
                </div>
                <div>
                  <span class="mg-ads-check-label">Phase 1 placements</span>
                  <div class="mg-ads-check-grid">
                    <label class="mg-ads-check"><input type="checkbox" name="placements[]" value="feed_sponsored_card" checked> Feed card</label>
                    <label class="mg-ads-check"><input type="checkbox" name="placements[]" value="sidebar_sponsored_card" checked> Sidebar card</label>
                    <label class="mg-ads-check"><input type="checkbox" name="placements[]" value="world_canvas_sponsored_pin"> World Canvas pin</label>
                    <label class="mg-ads-check"><input type="checkbox" name="placements[]" value="target_zone_sponsored_drop"> Target Zone drop</label>
                    <label class="mg-ads-check"><input type="checkbox" name="placements[]" value="inbox_recommendation"> Inbox recommendation</label>
                    <label class="mg-ads-check"><input type="checkbox" name="placements[]" value="claim_success_recommendation"> Claim success</label>
                  </div>
                </div>
                <div class="mg-ads-actions">
                  <button class="mg-btn mg-btn-primary" type="button" data-save-draft>Save Campaign</button>
                  <button class="mg-btn mg-btn-soft" type="button" data-new-draft>Save Draft</button>
                  <button class="mg-btn mg-btn-soft" type="button" data-submit-current>Submit for Review</button>
                </div>
                <p class="mg-ads-status" data-ads-status role="status"></p>
              </form>
            </article>

            <aside class="mg-ads-side-stack">
              <article class="mg-ads-panel">
                <h2>Sponsored card preview <span aria-hidden="true">ⓘ</span></h2>
                <div class="mg-sponsored-placement" data-ads-preview></div>
              </article>
              <article class="mg-ads-panel mg-ads-help-card">
                <h2>Need help?</h2>
                <p class="mg-ads-muted">Choose a product or upload campaign media, then tighten the headline and CTA for higher claim and redemption rates.</p>
                <button class="mg-btn mg-btn-soft" type="button" data-ads-tab-jump="analytics">View best practices →</button>
              </article>
            </aside>
          </div>
        </section>

        <section class="mg-ads-tab-panel" data-ads-tab-panel="preview">
          <div class="mg-ads-preview-layout">
            <article class="mg-ads-panel"><h2>Sponsored preview</h2><p class="mg-ads-muted">Use the Create Campaign tab to edit the preview in real time.</p><div class="mg-sponsored-placement" data-ads-preview-secondary></div></article>
            <article class="mg-ads-panel"><h2>Preview notes</h2><p class="mg-ads-muted">Preview cards use the same sponsored renderer as Feed, Sidebar, World Canvas, Inbox, and Claim Success placements.</p></article>
          </div>
        </section>

        <section class="mg-ads-tab-panel" data-ads-tab-panel="campaigns">
          <article class="mg-ads-panel">
            <div class="mg-ads-table-head"><h2>Merchant Campaigns</h2><div class="mg-ads-table-tools"><input type="search" data-ads-search placeholder="Search campaigns…"><button class="mg-btn mg-btn-primary" type="button" data-ads-tab-jump="create">+ New Campaign</button></div></div>
            <div class="mg-ads-list mg-ads-list-table" data-ads-list><div class="mg-ads-empty">Loading campaigns…</div></div>
          </article>
        </section>

        <section class="mg-ads-tab-panel" data-ads-tab-panel="analytics">
          <div class="mg-ads-analytics-layout">
            <article class="mg-ads-panel"><h2>Campaign performance</h2><p class="mg-ads-muted">Performance totals are loaded from the existing Campaign Ads reporting API.</p><div class="mg-ads-analytics-grid"><span><strong data-kpi="impressions">0</strong> impressions</span><span><strong data-kpi="clicks">0</strong> clicks</span><span><strong data-kpi="claims">0</strong> claims</span><span><strong data-kpi="redemptions">0</strong> redemptions</span></div><a class="mg-btn mg-btn-soft" href="/merchant-ad-performance.php">Open full analytics</a></article>
            <article class="mg-ads-panel"><h2>Best practices</h2><p class="mg-ads-muted">Use clear local value, connect the CTA to a real reward, assign only the surfaces where the ad makes sense, and review health alerts after publishing.</p></article>
          </div>
        </section>
      <?php endif; ?>
    </main>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>