<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'My Subscription | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'subscriptions';
$page_styles = [];
$page_scripts = ['/assets/js/account.js','/assets/js/subscription-activation-status.js'];
$user = mg_current_user();
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app mg-account-subscriptions-app" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-main">
    <?php if (!$user): ?>
      <section class="mg-account-guest mg-app-panel"><div class="mg-app-panel-head"><div><h2>Account access</h2><p>Sign in to continue to your subscription and workspace settings.</p></div></div><div class="mg-app-panel-body"><div class="mg-action-row"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a><a class="mg-btn mg-btn-ghost" href="/signup.php">Create account</a></div></div></section>
    <?php else: ?>
      <?php require __DIR__ . '/includes/account/subscriptions-view.php'; ?>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
