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
$page_styles = ['/assets/css/merchant-canvas.css','/assets/css/merchant-canvas-rewards.css','/assets/css/merchant-canvas-phase2.css','/assets/css/merchant-canvas-motion.css','/assets/css/merchant-canvas-drawer-layer.css','/assets/css/merchant-canvas-settings-drawers.css','/assets/css/merchant-canvas-drawer-fixes.css','/assets/css/merchant-canvas-customer-tabs.css','/assets/css/merchant-canvas-intelligence.css','/assets/css/merchant-canvas-store-health.css','/assets/css/merchant-canvas-mobile-icons.css','/assets/css/merchant-canvas-containment.css','/assets/css/merchant-canvas-manual-operations.css','/assets/css/sponsored-campaign-card.css'];
$page_scripts = $hasMerchantAccess ? ['/assets/js/merchant-canvas-manual-operations.js','/assets/js/merchant-canvas-drawer-coordinator.js','/assets/js/merchant-canvas-mobile-icons.js','/assets/js/sponsored-campaign-card.js'] : [];
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
<section class="mg-app-shell mg-agent-app mg-store-canvas" data-merchant-canvas>
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <div class="mg-app-workspace mg-canvas-workspace">
    <?php if (!$hasMerchantAccess): ?>
      <article class="mg-canvas-empty-card">
        <span class="mg-canvas-eyebrow">Merchant access required</span>
        <h1>Store Canvas is for merchant accounts.</h1>
        <p>Upgrade or sign into a merchant account to view customer avatars, campaign agents, direct store-session messages, and Store Canvas rewards.</p>
        <a class="mg-btn mg-btn-primary" href="/pricing.php">View merchant packages</a>
      </article>
    <?php else: ?>
      <section class="mg-canvas-shell">
        <div class="mg-canvas-containment-banner" role="status" data-canvas-containment-banner>
          <div>
            <span class="mg-canvas-eyebrow">Production containment active</span>
            <strong>Automatic movement, proximity chat, and overlap-triggered campaigns are paused.</strong>
            <p>Manual customer actions now use server-backed CRM safeguards, session validation, and protected request keys.</p>
          </div>
          <div>
            <span class="mg-canvas-containment-state">Manual operations</span>
            <button type="button" class="mg-btn mg-btn-secondary" data-canvas-refresh>Refresh live customers</button>
          </div>
        </div>

        <div class="mg-canvas-command-strip" aria-label="Store Canvas summary">
          <article><span>Inside Now</span><strong data-canvas-active-count>0</strong></article>
          <article><span>Today Entries</span><strong data-canvas-today-entries>0</strong></article>
          <article><span>Canvas Events</span><strong data-canvas-today-events>0</strong></article>
          <article><span>History Rows</span><strong data-canvas-history-rows>0</strong></article>
        </div>

        <div class="mg-canvas-grid mg-canvas-grid-full">
          <section class="mg-canvas-stage" aria-label="Live store canvas">
            <span class="mg-canvas-live-pill mg-canvas-live-pill-hidden" data-canvas-live-pill aria-live="polite">Checking database</span>

            <div class="mg-canvas-state-banner mg-canvas-state-hidden" data-canvas-state role="status" aria-live="polite">
              Database check pending.
            </div>

            <details class="mg-canvas-diagnostics" data-canvas-diagnostics>
              <summary>
                <span>Database diagnostics</span>
                <strong data-canvas-health-status>Not checked</strong>
              </summary>
              <div class="mg-canvas-health-grid" data-canvas-health></div>
              <button type="button" class="mg-btn mg-btn-secondary" data-canvas-health-refresh>Run diagnostics</button>
            </details>

            <div class="mg-canvas-map" data-canvas-map>
              <div class="mg-canvas-agent-node mg-canvas-merchant-node" aria-label="Merchant agent">
                <span class="mg-canvas-agent-icon">
                  <?php if ($merchantAvatarUrl !== ''): ?>
                    <img src="<?php echo mg_e($merchantAvatarUrl); ?>" alt="">
                  <?php else: ?>
                    <?php echo mg_e($merchantInitials); ?>
                  <?php endif; ?>
                </span>
                <strong><?php echo mg_e($merchantDisplayName); ?></strong>
                <small>Merchant Agent · protected manual messaging · protected manual rewards</small>
              </div>
              <div class="mg-canvas-avatar-layer" data-canvas-customers aria-live="polite"></div>
              <div class="mg-sponsored-map-layer" data-mg-ad-placement="world_canvas_sponsored_pin" data-mg-ad-limit="5" aria-label="Sponsored World Canvas pins"></div>
              <div class="mg-sponsored-map-layer" data-mg-ad-placement="target_zone_sponsored_drop" data-mg-ad-limit="5" aria-label="Sponsored Target Zone drops"></div>
              <article class="mg-canvas-empty-state" data-canvas-empty>
                <span>No avatars inside yet</span>
                <p data-canvas-empty-copy>Customer avatars will appear here when shoppers enter from merchant feed posts.</p>
              </article>
            </div>
          </section>
        </div>
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
        <p>Click a customer avatar on the Store Canvas to load CRM details.</p>
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
