<?php
$page_scripts = $page_scripts ?? [];
$late_styles = [];
if (($page_section ?? '') === 'feed') {
    $page_scripts[] = '/assets/js/social-feed-attachments.js';
    $page_scripts[] = '/assets/js/social-feed-attachment-cards.js';
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
$core_scripts = [
    '/assets/js/microgifter.js','/assets/js/header-signals.js','/assets/js/api-client.js','/assets/js/agent-folder-counts.js',
    '/assets/js/agent-global-search.js','/assets/js/customer-commerce.js','/assets/js/cart.js','/assets/js/auth.js',
    '/assets/js/auth-state.js','/assets/js/onboarding.js','/assets/js/agent-tabs.js','/assets/js/agent-controls.js',
    '/assets/js/agent-toolbar-state.js','/assets/js/agent-sidebar.js','/assets/js/agent-items.js','/assets/js/media-delivery.js',
    '/assets/js/gift-stream-launch.js','/assets/js/merchant-claim.js','/assets/js/agent-tools.js',
];
$scripts = array_values(array_unique(array_merge($core_scripts, $page_scripts)));
$user = mg_current_user();
$user_permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$user_roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$can_sales_crm = $user && (in_array('sales.leads.view_own', $user_permissions, true) || in_array('sales.leads.view_all', $user_permissions, true) || in_array('super_admin', $user_roles, true));
$can_intelligence = $user && (in_array('intelligence.dashboard.view', $user_permissions, true) || in_array('demand.dashboard.view', $user_permissions, true) || in_array('super_admin', $user_roles, true));
?>
</main>
<footer class="mg-site-footer" data-mg-universal-footer>
  <div class="mg-footer-inner">
    <div class="mg-footer-brand">
      <a class="mg-brand mg-footer-logo" href="/index.php" aria-label="Microgifter home"><span>Microgifter</span></a>
      <p>Pre-purchase gifts, local rewards, and agent-assisted gifting.</p>
      <p class="mg-footer-copyright">&copy; <?= date('Y') ?> Microgifter. All rights reserved.</p>
    </div>
    <nav class="mg-footer-nav" aria-label="Footer navigation"><a href="/learn-more.php">Learn more</a><a href="/discover.php">Discover</a><a href="/feed.php">Feed</a><a href="/build.php">Build</a><a href="/agent.php">Agent</a><a href="/account.php">Account</a><?php if ($user): ?><a href="/commitments.php">Commitments</a><?php endif; ?><?php if ($can_intelligence): ?><a href="/intelligence.php">Intelligence</a><?php endif; ?><?php if ($can_sales_crm): ?><a href="/sales-crm.php">CRM</a><?php endif; ?><a href="/signin.php">Sign in</a></nav>
  </div>
</footer>
<?php foreach (array_unique($late_styles) as $style): ?><link rel="stylesheet" href="<?= mg_e($style) ?>"><?php endforeach; ?>
<?php foreach ($scripts as $script): ?><script src="<?= mg_e($script) ?>" defer></script><?php endforeach; ?>
</body></html>
