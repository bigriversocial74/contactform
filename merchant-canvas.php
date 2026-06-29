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
$page_scripts = ['/assets/js/merchant-canvas.js','/assets/js/merchant-canvas-rewards.js','/assets/js/merchant-canvas-motion.js','/assets/js/merchant-canvas-automation-rules.js','/assets/js/merchant-canvas-merchant-settings.js','/assets/js/merchant-canvas-drawer-coordinator.js'];
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
        <header class="mg-canvas-topbar" aria-label="Store Canvas live metrics">
          <div class="mg-canvas-topbar-title">
            <span class="mg-canvas-eyebrow">Store Canvas</span>
            <strong>Live store session map</strong>
          </div>
          <div class="mg-canvas-header-stats" aria-label="Store Canvas summary">
            <article><span>Inside Now</span><strong data-canvas-active-count>0</strong></article>
            <article><span>Today Entries</span><strong data-canvas-today-entries>0</strong></article>
            <article><span>Canvas Events</span><strong data-canvas-today-events>0</strong></article>
            <article><span>History Rows</span><strong data-canvas-history-rows>0</strong></article>
          </div>
        </header>

        <div class="mg-canvas-grid mg-canvas-grid-full">
          <section class="mg-canvas-stage" aria-label="Live store canvas">
            <span class="mg-canvas-live-pill mg-canvas-live-pill-hidden" data-canvas-live-pill>Checking database</span>

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
                <small>Merchant Agent · campaigns · rewards</small>
                <em>Online</em>
              </div>
              <div class="mg-canvas-avatar-layer" data-canvas-customers></div>
              <div class="mg-canvas-trigger-layer" data-canvas-triggers></div>
              <article class="mg-canvas-empty-state" data-canvas-empty>
                <span>No avatars inside yet</span>
                <p data-canvas-empty-copy>Customer avatars will appear here when shoppers enter from merchant feed posts. Click an avatar to open the In-Store Chat sidebar.</p>
              </article>
            </div>
          </section>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <aside class="mg-canvas-crm-drawer mg-canvas-chat-drawer" data-canvas-drawer aria-hidden="true">
    <div class="mg-canvas-drawer-head">
      <div>
        <span class="mg-canvas-eyebrow">In-Store Chat</span>
        <h2 data-drawer-name>Customer chats</h2>
        <p class="mg-canvas-chat-presence"><span></span> Online · <strong data-canvas-active-count>0</strong> visitor(s)</p>
      </div>
      <button type="button" data-drawer-close aria-label="Close in-store chat drawer">×</button>
    </div>

    <nav class="mg-canvas-chat-tabs" data-chat-tabs aria-label="Customer chat tabs">
      <button type="button" disabled>No active chats</button>
    </nav>

    <div class="mg-canvas-drawer-body mg-canvas-chat-body" data-drawer-body>
      <div class="mg-canvas-chat-empty">
        <strong>Select a customer avatar</strong>
        <p>Customer conversations open here as a slide-out sidebar while the Store Canvas stays full size.</p>
      </div>
    </div>

    <form class="mg-canvas-message-form mg-canvas-chat-composer" data-message-form>
      <label for="mg-canvas-message">In-store reply</label>
      <div class="mg-canvas-chat-input-row">
        <textarea id="mg-canvas-message" name="message" rows="2" maxlength="1000" placeholder="Type a message..." required disabled></textarea>
        <button class="mg-btn mg-btn-primary" type="submit" disabled data-message-submit>Send</button>
      </div>
      <p class="mg-canvas-form-status" data-message-status role="status"></p>
    </form>
  </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
