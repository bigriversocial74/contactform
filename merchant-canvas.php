<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$user = mg_require_auth('/signin.php', '/merchant-canvas.php');
$pdo = mg_db();
$page_title = 'Merchant Store Canvas | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'store-canvas';
$page_styles = ['/assets/css/merchant-canvas.css','/assets/css/merchant-canvas-rewards.css','/assets/css/merchant-canvas-phase2.css','/assets/css/merchant-canvas-motion.css','/assets/css/merchant-canvas-drawer-layer.css','/assets/css/merchant-canvas-settings-drawers.css','/assets/css/merchant-canvas-drawer-fixes.css'];
$page_scripts = ['/assets/js/merchant-canvas.js','/assets/js/merchant-canvas-rewards.js','/assets/js/merchant-canvas-motion.js','/assets/js/merchant-canvas-automation-rules.js','/assets/js/merchant-canvas-merchant-settings.js','/assets/js/merchant-canvas-drawer-coordinator.js','/assets/js/merchant-canvas-trigger-control-suite.js'];
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
        if ($profileName !== '') {
            $merchantDisplayName = $profileName;
        }
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
    <?php if (!mg_user_has_merchant_access($user, $pdo)): ?>
      <article class="mg-canvas-empty-card">
        <span class="mg-canvas-eyebrow">Merchant access required</span>
        <h1>Store Canvas is for merchant accounts.</h1>
        <p>Upgrade or sign into a merchant account to view customer avatars, campaign agents, direct store-session messages, and Store Canvas rewards.</p>
        <a class="mg-btn mg-btn-primary" href="/pricing.php">View merchant packages</a>
      </article>
    <?php else: ?>
      <section class="mg-canvas-shell">
        <div class="mg-canvas-grid mg-canvas-grid-full">
          <section class="mg-canvas-stage" aria-label="Live store canvas">
            <span class="mg-canvas-live-pill mg-canvas-live-pill-hidden" data-canvas-live-pill>Checking database</span>

            <div class="mg-canvas-command-strip" aria-label="Store Canvas summary">
              <article><span>Inside now</span><strong data-canvas-active-count>0</strong></article>
              <article><span>Today entries</span><strong data-canvas-today-entries>0</strong></article>
              <article><span>Canvas events</span><strong data-canvas-today-events>0</strong></article>
              <article><span>History rows</span><strong data-canvas-history-rows>0</strong></article>
            </div>

            <div class="mg-canvas-state-banner mg-canvas-state-hidden" data-canvas-state>
              Database check pending.
            </div>

            <div class="mg-canvas-map" data-canvas-map>
              <div class="mg-canvas-agent-node mg-canvas-merchant-node" data-merchant-avatar-settings>
                <span class="mg-canvas-agent-icon">
                  <?php if ($merchantAvatarUrl !== ''): ?>
                    <img src="<?php echo mg_e($merchantAvatarUrl); ?>" alt="">
                  <?php else: ?>
                    <?php echo mg_e($merchantInitials); ?>
                  <?php endif; ?>
                </span>
                <strong><?php echo mg_e($merchantDisplayName); ?></strong>
                <small>Merchant Agent · campaigns · rewards · CRM</small>
              </div>
              <div class="mg-canvas-avatar-layer" data-canvas-customers></div>
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

  <aside class="mg-canvas-crm-drawer" data-canvas-drawer aria-hidden="true">
    <div class="mg-canvas-drawer-head">
      <div>
        <span class="mg-canvas-eyebrow">Customer CRM</span>
        <h2 data-drawer-name>Select an avatar</h2>
      </div>
      <button type="button" data-drawer-close aria-label="Close customer CRM drawer">x</button>
    </div>
    <div class="mg-canvas-drawer-body" data-drawer-body>
      <p>Click a customer avatar on the Store Canvas to load CRM details.</p>
    </div>
    <form class="mg-canvas-message-form" data-message-form>
      <label for="mg-canvas-message">Direct message</label>
      <textarea id="mg-canvas-message" name="message" rows="4" maxlength="1000" placeholder="Send a message to this customer's Messages center..." required disabled></textarea>
      <button class="mg-btn mg-btn-primary" type="submit" disabled data-message-submit>Send Message</button>
      <p class="mg-canvas-form-status" data-message-status role="status"></p>
    </form>
  </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
