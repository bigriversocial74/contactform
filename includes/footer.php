<?php
$page_scripts = $page_scripts ?? [];
$late_styles = ['/assets/css/universal-footer.css', '/assets/css/market-alerts.css'];
if (($page_manifest['id'] ?? '') === 'developer-docs') {
    $late_styles[] = '/assets/css/docs-polish.css';
}
if (($page_body_class ?? '') === 'mg-admin-merchant-catalog-page') {
    require __DIR__ . '/footer-mc-snippet.php';
}
if (($page_section ?? '') === 'feed') {
    $page_scripts[] = '/assets/js/social-feed-attachments.js';
    $page_scripts[] = '/assets/js/social-feed-attachment-cards.js';
    $page_scripts[] = '/assets/js/store-presence-feed.js';
    $late_styles[] = '/assets/css/store-presence-feed.css';
}
if (($page_body_class ?? '') === 'mg-admin-moderation-page') {
    $page_scripts[] = '/assets/js/admin-moderation.js';
    $page_scripts[] = '/assets/js/content-' . 'review-actions.js';
    $late_styles[] = '/assets/css/content-review-ui.css';
}
if (($page_body_class ?? '') === 'mg-admin-users-page') {
    $page_scripts[] = '/assets/js/admin-user-detail-drawer.js';
    $page_scripts[] = '/assets/js/admin-user-management.js';
    $late_styles[] = '/assets/css/admin-user-detail-drawer.css';
    $late_styles[] = '/assets/css/admin-user-management.css';
}
if (($page_body_class ?? '') === 'mg-admin-commerce-page') {
    $page_scripts[] = '/assets/js/admin-commerce-inspector.js';
    $page_scripts[] = '/assets/js/admin-commerce-workflow.js';
    $late_styles[] = '/assets/css/admin-commerce-drawer.css';
}
if ((string) ($page_manifest['id'] ?? '') === 'home') {
    $page_scripts[] = '/assets/js/home-sticky-usa-map.js';
}
if ((string) ($page_manifest['id'] ?? '') === 'discover') {
    $page_scripts[] = '/assets/js/discover-state-results-link.js';
}
if ((string) ($page_manifest['id'] ?? '') === 'merchant-canvas') {
    $page_scripts[] = '/assets/js/merchant-canvas-behavior-post.js';
    $page_scripts[] = '/assets/js/merchant-canvas-trigger-manager.js';
}
$core_scripts = [
    '/assets/js/microgifter.js','/assets/js/universal-header.js','/assets/js/header-signals.js','/assets/js/create-menu.js','/assets/js/builder-publish-errors.js','/assets/js/api-client.js','/assets/js/global-post-composer.js','/assets/js/agent-folder-counts.js',
    '/assets/js/agent-global-search.js','/assets/js/customer-commerce.js','/assets/js/cart.js','/assets/js/auth.js',
    '/assets/js/auth-state.js','/assets/js/onboarding.js','/assets/js/agent-tabs.js','/assets/js/agent-controls.js',
    '/assets/js/agent-toolbar-state.js','/assets/js/agent-sidebar.js','/assets/js/agent-items.js','/assets/js/media-delivery.js',
    '/assets/js/gift-stream-launch.js','/assets/js/merchant-claim.js','/assets/js/agent-tools.js','/assets/js/scanner-device-session.js','/assets/js/market-alerts.js','/assets/js/public-market-ticker.js','/assets/js/subscription-checkout.js',
];
$scripts = array_values(array_unique(array_merge($core_scripts, $page_scripts)));
$user = mg_current_user();
if ($user) {
    $late_styles[] = '/assets/css/store-chat-widget.css';
    $scripts[] = '/assets/js/store-chat-widget.js';
}
$user_permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$user_roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$can_sales_crm = $user && (in_array('sales.leads.view_own', $user_permissions, true) || in_array('sales.leads.view_all', $user_permissions, true) || in_array('super_admin', $user_roles, true));
$can_intelligence = $user && (in_array('intelligence.dashboard.view', $user_permissions, true) || in_array('demand.dashboard.view', $user_permissions, true) || in_array('super_admin', $user_roles, true));
?>
</main>
<footer class="mg-site-footer mg-universal-footer" data-mg-universal-footer>
  <div class="mg-footer-shell">
    <section class="mg-footer-brand-panel" aria-label="Microgifter footer overview">
      <a class="mg-brand mg-footer-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span>Microgifter</span></a>
      <p>Rewards, tokenized local experiences, and agent-ready gifting tools for local commerce.</p>
      <div class="mg-footer-market-strip" aria-label="Experience market summary" data-footer-market-strip>
        <span><strong>MGFT</strong> Opening soon</span>
        <span><strong>MERCH</strong> Snapshots</span>
        <span><strong>SCORE</strong> Beta</span>
      </div>
    </section>

    <nav class="mg-footer-link-grid" aria-label="Footer navigation">
      <div class="mg-footer-column">
        <h2>Platform</h2>
        <a href="/discover.php">Explore</a>
        <a href="/pricing.php">Pricing</a>
        <a href="/investors.php">Investors</a>
        <a href="/developer-docs.php">Developer Docs</a>
        <a href="/learn-more.php">Book A Demo</a>
        <a href="/campaign.php">Campaigns</a>
      </div>
      <div class="mg-footer-column">
        <h2>Developers</h2>
        <a href="/developer-docs.php#overview">Overview</a>
        <a href="/developer-docs.php#quickstart">Quickstart</a>
        <a href="/developer-docs.php#authentication">Authentication</a>
        <a href="/developer-docs.php#webhooks">Webhooks</a>
      </div>
      <div class="mg-footer-column">
        <h2>Account</h2>
        <?php if ($user): ?>
          <a href="/inbox.php">IN/OUT Box</a>
          <a href="/feed.php">My Feed</a>
          <a href="/account.php">Profile Settings</a>
          <a href="/commitments.php">Commitments</a>
        <?php else: ?>
          <a href="/signin.php">Sign In</a>
          <a href="/signup.php">Create Account</a>
          <a href="/forgot-password.php">Reset Password</a>
          <a href="/discover.php">Browse Offers</a>
        <?php endif; ?>
      </div>
      <div class="mg-footer-column">
        <h2>Workspace</h2>
        <a href="/build.php">Build</a>
        <a href="/agent.php">Agent</a>
        <?php if (!$user && (string) ($page_manifest['id'] ?? '') === 'index'): ?><a href="https://labs.microgifter.com">Training Labs</a><?php endif; ?>
        <?php if ($can_intelligence): ?><a href="/intelligence.php">Intelligence</a><?php endif; ?>
        <?php if ($can_sales_crm): ?><a href="/sales-crm.php">CRM</a><?php endif; ?>
        <a href="/account-commerce.php">Commerce Center</a>
        <?php if ($user && function_exists('mg_user_has_merchant_access') && mg_user_has_merchant_access($user)): ?><a href="/merchant-canvas.php">Store Canvas</a><?php endif; ?>
      </div>
    </nav>

    <div class="mg-footer-bottom">
      <p>&copy; <?= date('Y') ?> Microgifter. All rights reserved.</p>
      <div class="mg-footer-bottom-links" aria-label="Footer utility links">
        <a href="/index.php">Home</a>
        <a href="/pricing.php">Pricing</a>
        <a href="/investors.php">Investors</a>
        <a href="/developer-docs.php">Docs</a>
        <a href="/signin.php">Sign In</a>
      </div>
    </div>
  </div>
</footer>
<?php if (!$user): ?>
<script>
(() => {
  const addPricingLink = () => {
    const addLink = (nav, beforeDemo) => {
      if (!nav || nav.querySelector('a[href="/pricing.php"]')) return;
      const link = document.createElement('a');
      link.href = '/pricing.php';
      link.textContent = 'Pricing';
      if (beforeDemo) {
        const demo = Array.from(nav.querySelectorAll('a')).find((item) => item.textContent.trim().toLowerCase().includes('demo'));
        if (demo) {
          nav.insertBefore(link, demo);
          return;
        }
      }
      nav.appendChild(link);
    };
    document.querySelectorAll('.mg-public-nav').forEach((nav) => addLink(nav, false));
    document.querySelectorAll('.mg-public-mobile-nav').forEach((nav) => addLink(nav, true));
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addPricingLink, { once:true });
  } else {
    addPricingLink();
  }
})();
</script>
<?php endif; ?>
<?php foreach (array_unique($late_styles) as $style): ?><link rel="stylesheet" href="<?= mg_e($style) ?>"><?php endforeach; ?>
<?php foreach (array_unique($scripts) as $script): ?><script src="<?= mg_e($script) ?>" defer></script><?php endforeach; ?>
</body></html>