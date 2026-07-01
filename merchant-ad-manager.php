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
$page_body_class = 'mg-ad-manager-page';
$page_styles = ['/assets/css/merchant-ad-manager.css','/assets/css/sponsored-campaign-card.css'];
$page_scripts = ['/assets/js/sponsored-campaign-card.js','/assets/js/merchant-ad-manager.js'];
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
    <main class="mg-ads-shell" data-ads-manager data-csrf-token="<?php echo mg_e($csrfToken); ?>" data-merchant-name="<?php echo mg_e($merchantName); ?>">
      <section class="mg-ads-hero">
        <article class="mg-ads-hero-card">
          <span class="mg-ads-eyebrow">Campaign Ads Manager · Phase 1</span>
          <h1>Boost campaigns and sponsor local drops.</h1>
          <p>Promote rewards, gift offers, campaign drops, and target-zone activations across the Feed, Sidebar, World Canvas, and Target Zones. Phase 1 measures action: impressions, clicks, claims, wallet saves, redemptions, CRM actions, and Pre Sale Revenue impact.</p>
        </article>
        <aside class="mg-ads-kpi-grid" aria-label="Advertising performance summary">
          <div class="mg-ads-kpi"><span>Impressions</span><strong data-kpi="impressions">0</strong></div>
          <div class="mg-ads-kpi"><span>Clicks</span><strong data-kpi="clicks">0</strong></div>
          <div class="mg-ads-kpi"><span>Claims</span><strong data-kpi="claims">0</strong></div>
          <div class="mg-ads-kpi"><span>Redemptions</span><strong data-kpi="redemptions">0</strong></div>
        </aside>
      </section>

      <?php if (!mg_ads_user_can_merchant($user, $pdo)): ?>
        <section class="mg-ads-panel"><div class="mg-ads-alert">Merchant access is required to use Campaign Ads Manager.</div></section>
      <?php else: ?>
        <?php if (!$schema['ready']): ?>
          <section class="mg-ads-panel"><div class="mg-ads-alert">SQL migration required: run <strong>database/microgifter_ads_manager_phase1.sql</strong> before saving ad campaigns.</div></section>
        <?php endif; ?>

        <section class="mg-ads-grid">
          <article class="mg-ads-panel">
            <h2>Create Campaign Boost / Sponsored Local Drop</h2>
            <form class="mg-ads-form" data-ad-form onsubmit="return false;">
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
              <div class="mg-ads-field"><label for="ad-image">Image URL</label><input id="ad-image" name="image_url" placeholder="/images/example-offer.png"></div>
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
                </div>
              </div>
              <div class="mg-ads-actions">
                <button class="mg-btn mg-btn-soft" type="button" data-new-draft>New Draft</button>
                <button class="mg-btn mg-btn-primary" type="button" data-save-draft>Save Draft</button>
                <button class="mg-btn mg-btn-soft" type="button" data-submit-current>Submit for Review</button>
              </div>
              <p class="mg-ads-status" data-ads-status role="status"></p>
            </form>
          </article>

          <aside class="mg-ads-preview">
            <article class="mg-ads-panel">
              <h2>Sponsored card preview</h2>
              <div class="mg-sponsored-placement" data-ads-preview></div>
            </article>
            <article class="mg-ads-panel" style="margin-top:18px">
              <h2>Your ad campaigns</h2>
              <div class="mg-ads-list" data-ads-list><div class="mg-ads-empty">Loading campaigns…</div></div>
            </article>
          </aside>
        </section>
      <?php endif; ?>
    </main>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
