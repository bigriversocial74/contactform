<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/account/subscription-authority.php';

$page_title='My Subscription | Microgifter';
$page_section='account';
$header_mode='account';
$agent_tab='subscriptions';
$use_inbox_sidebar=true;
$agent_sidebar_mode='subscriptions';
$page_styles=[
    '/assets/css/personal-agent-chat-history.css?v=1.4.0',
    '/assets/css/subscription-billing-v2.css?v=1.0.0',
    '/assets/css/subscription-checkout-completion-v1.css?v=1.0.0',
    '/assets/css/subscription-agent-access-v1.css?v=1.0.0',
];
$page_scripts=[
    '/assets/js/account.js',
    '/assets/js/personal-agent-chat-history.js?v=1.1.0',
    '/assets/js/subscription-activation-status.js?v=2.0.0',
    '/assets/js/subscription-billing-v2.js?v=1.0.0',
    '/assets/js/subscription-checkout-completion-v1.js?v=1.0.0',
    '/assets/js/subscription-agent-access-v1.js?v=1.0.0',
];
$user=mg_current_user();
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app mg-account-subscriptions-app" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/personal-agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-main">
    <?php if(!$user): ?>
      <section class="mg-account-guest mg-app-panel"><div class="mg-app-panel-head"><div><h2>Account access</h2><p>Sign in to continue to your subscription and workspace settings.</p></div></div><div class="mg-app-panel-body"><div class="mg-action-row"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a><a class="mg-btn mg-btn-ghost" href="/signup.php">Create account</a></div></div></section>
    <?php else: ?>
      <?php require __DIR__ . '/includes/account/subscriptions-view.php'; ?>
      <div data-subscription-billing-v2-root></div>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>