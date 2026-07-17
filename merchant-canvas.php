<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$user = mg_require_auth('/signin.php', '/merchant-canvas.php');
$pdo = mg_db();
$hasMerchantAccess = mg_user_has_merchant_access($user, $pdo);
$page_title = 'Merchant Store Canvas | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'store-canvas';
$agent_sidebar_mode = 'merchant';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/personal-agent-chat-history.css?v=1.4.0',
    '/assets/css/personal-agent-sidebar-cleanup.css?v=1.0.0',
    '/assets/css/merchant-canvas.css',
    '/assets/css/merchant-canvas-rewards.css',
    '/assets/css/merchant-canvas-phase2.css',
    '/assets/css/merchant-canvas-motion.css',
    '/assets/css/merchant-canvas-drawer-layer.css',
    '/assets/css/merchant-canvas-settings-drawers.css',
    '/assets/css/merchant-canvas-drawer-fixes.css',
    '/assets/css/merchant-canvas-customer-tabs.css',
    '/assets/css/merchant-canvas-intelligence.css',
    '/assets/css/merchant-canvas-store-health.css',
    '/assets/css/merchant-canvas-mobile-icons.css',
    '/assets/css/merchant-canvas-containment.css',
    '/assets/css/merchant-canvas-manual-operations.css',
    '/assets/css/merchant-canvas-customer-analytics.css',
    '/assets/css/sponsored-campaign-card.css',
    '/assets/css/merchant-canvas-restoration.css',
    '/assets/css/merchant-canvas-mobile-stats.css',
    '/assets/css/merchant-canvas-header-hud.css',
    '/assets/css/merchant-canvas-behavior-memory.css',
];
$page_scripts = $hasMerchantAccess ? [
    '/assets/js/merchant-agent-sidebar-history-standalone.js?v=1.0.0',
    '/assets/js/merchant-canvas-manual-operations.js',
    '/assets/js/merchant-canvas-movement-continuity.js',
    '/assets/js/merchant-canvas-customer-analytics.js',
    '/assets/js/merchant-canvas-drawer-coordinator.js',
    '/assets/js/merchant-canvas-mobile-icons.js',
    '/assets/js/sponsored-campaign-card.js',
    '/assets/js/merchant-canvas-visual-restoration.js',
    '/assets/js/merchant-canvas-campaign-recommendations.js',
    '/assets/js/merchant-canvas-header-hud.js',
    '/assets/js/merchant-canvas-behavior-memory.js',
    '/assets/js/merchant-canvas-trigger-engine.js',
    '/assets/js/merchant-canvas-trigger-orchestration.js',
] : [];
$page_manifest = [
    'id' => 'merchant-canvas',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-merchant-canvas-page',
    'onboarding' => ['enabled' => false, 'page' => 'merchant-canvas', 'sections' => []],
];

$merchantDisplayName = trim((string)($user['display_name'] ?? $user['name'] ?? 'Merchant Agent')) ?: 'Merchant Agent';
$merchantAvatarUrl = '';
try {
    $profile = $pdo->prepare('SELECT display_name, avatar_url FROM public_profiles WHERE user_id=? LIMIT 1');
    $profile->execute([(int)$user['id']]);
    $row = $profile->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $profileName = trim((string)($row['display_name'] ?? ''));
        if ($profileName !== '') $merchantDisplayName = $profileName;
        $avatarCandidate = trim((string)($row['avatar_url'] ?? ''));
        if ($avatarCandidate !== '' && strlen($avatarCandidate) <= 600 && preg_match('/[[:cntrl:]]/', $avatarCandidate) !== 1) {
            if ((str_starts_with($avatarCandidate, '/') && !str_starts_with($avatarCandidate, '//')) || filter_var($avatarCandidate, FILTER_VALIDATE_URL) !== false) {
                $merchantAvatarUrl = $avatarCandidate;
            }
        }
    }
} catch (Throwable) {
    $merchantAvatarUrl = '';
}
$merchantInitials = 'MG';
$merchantNameParts = preg_split('/\s+/u', $merchantDisplayName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
if ($merchantNameParts !== []) {
    $merchantInitials = strtoupper(substr((string)$merchantNameParts[0], 0, 1) . substr((string)($merchantNameParts[1] ?? ''), 0, 1));
    $merchantInitials = $merchantInitials !== '' ? $merchantInitials : 'MG';
}

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-app mg-store-canvas mg-store-canvas-restored" data-merchant-canvas>
  <?php require __DIR__ . '/includes/personal-agent-sidebar.php'; ?>
  <div class="mg-app-workspace mg-canvas-workspace">
    <?php if (!$hasMerchantAccess): ?>
      <article class="mg-canvas-empty-card">
        <span class="mg-canvas-eyebrow">Merchant access required</span>
        <h1>Store Canvas is for merchant accounts.</h1>
        <p>Upgrade or sign into a merchant account to view customer avatars, campaign recommendations, direct store-session messages, and merchant CRM intelligence.</p>
        <a class="mg-btn mg-btn-primary" href="/pricing.php">View merchant packages</a>
      </article>
    <?php else: ?>
      <section class="mg-canvas-shell mg-canvas-shell-restored" aria-label="Merchant Store Canvas">
        <section class="mg-canvas-stage mg-canvas-stage-restored" aria-label="Live store canvas">
          <div class="mg-canvas-map mg-canvas-map-restored" data-canvas-map>
            <header class="mg-canvas-hud" aria-label="Store Canvas controls and live summary">
              <div class="mg-canvas-hud-status">
                <span class="mg-canvas-live-pill" data-canvas-live-pill aria-live="polite">Checking database</span>
              </div>
              <div class="mg-canvas-hud-stats" aria-label="Store Canvas summary">
                <article><span>Inside</span><strong data-canvas-active-count>0</strong></article>
                <article><span>Entries</span><strong data-canvas-today-entries>0</strong></article>
                <article><span>Events</span><strong data-canvas-today-events>0</strong></article>
                <article><span>History</span><strong data-canvas-history-rows>0</strong></article>
              </div>
              <div class="mg-canvas-hud-actions">
                <button type="button" class="mg-canvas-hud-button" data-canvas-refresh>Refresh</button>
                <button type="button" class="mg-canvas-hud-button is-primary" data-canvas-open-control>Control Center</button>
              </div>
            </header>

            <div class="mg-canvas-agent-node mg-canvas-merchant-node" data-canvas-control-center role="button" tabindex="0" aria-label="Open Merchant Control Center">
              <span class="mg-canvas-agent-icon">
                <?php if ($merchantAvatarUrl !== ''): ?>
                  <img src="<?php echo mg_e($merchantAvatarUrl); ?>" alt="">
                <?php else: ?>
                  <?php echo mg_e($merchantInitials); ?>
                <?php endif; ?>
              </span>
              <strong><?php echo mg_e($merchantDisplayName); ?></strong>
              <small data-canvas-agent-status>Merchant Agent · campaigns · CRM · intelligence</small>
            </div>

            <div class="mg-canvas-server-zone-layer" data-canvas-server-zones aria-label="Campaign trigger zones"></div>
            <div class="mg-canvas-avatar-layer" data-canvas-customers aria-live="polite"></div>
            <div class="mg-sponsored-map-layer" data-mg-ad-placement="world_canvas_sponsored_pin" data-mg-ad-limit="5" aria-label="Sponsored World Canvas pins"></div>
            <div class="mg-sponsored-map-layer" data-mg-ad-placement="target_zone_sponsored_drop" data-mg-ad-limit="5" aria-label="Sponsored Target Zone drops"></div>

            <article class="mg-canvas-empty-state" data-canvas-empty>
              <span>No customers inside yet</span>
              <p data-canvas-empty-copy>Customer avatars will appear and develop merchant-scoped behavior memory as shoppers return, explore products, join campaigns, claim rewards, and redeem.</p>
            </article>

            <div class="mg-canvas-legend" aria-label="Canvas legend">
              <span><i class="is-customer"></i> Live customer</span>
              <span><i class="is-zone"></i> Campaign zone</span>
              <span><i class="is-guarded"></i> Server validation required</span>
            </div>
          </div>
        </section>
      </section>
    <?php endif; ?>
  </div>

  <?php if ($hasMerchantAccess): ?>
    <aside class="mg-canvas-crm-drawer" data-canvas-drawer role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="mg-canvas-drawer-title" tabindex="-1">
      <div class="mg-canvas-drawer-head">
        <div>
          <span class="mg-canvas-eyebrow">Customer CRM</span>
          <h2 id="mg-canvas-drawer-title" data-drawer-name>Select an avatar</h2>
        </div>
        <button type="button" data-drawer-close aria-label="Close customer CRM drawer">&times;</button>
      </div>
      <div class="mg-canvas-drawer-body" data-drawer-body aria-live="polite">
        <p>Click a customer avatar to load the complete CRM timeline, merchant-customer memory, probability projections, messages, campaigns, and reward history.</p>
      </div>
      <form class="mg-canvas-message-form" data-message-form>
        <label for="mg-canvas-message">Direct message</label>
        <textarea id="mg-canvas-message" name="message" rows="4" maxlength="1000" placeholder="Send a direct message to this customer..." required disabled></textarea>
        <button class="mg-btn mg-btn-primary" type="submit" disabled data-message-submit>Send Message</button>
        <p class="mg-canvas-form-status" data-message-status role="status" aria-live="polite"></p>
      </form>
    </aside>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
