<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/training-lab-launch.php';

$user = mg_require_auth('/signin.php', '/training-lab.php');
$page_title = 'Training Lab | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$appSidebarActive = 'training-lab';
$launchReady = mg_training_lab_launch_ready();
$config = mg_training_lab_launch_config();
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-main">
    <section class="mg-app-panel mg-account-pane is-active">
      <div class="mg-app-panel-head">
        <div>
          <span class="mg-eyebrow">Shared account access</span>
          <h1>Microgifter Training Lab</h1>
          <p>Open proof-based campaigns, participant tasks, reviews, and training rewards using your current Microgifter account.</p>
        </div>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-account-section">
          <h2>Secure account handoff</h2>
          <p>Microgifter creates a short-lived signed identity assertion from your authenticated server session. Your password and password hash are never sent to Training Lab.</p>
        </div>

        <?php if ($launchReady): ?>
          <form method="post" action="/api/training-lab/launch.php" class="mg-action-row">
            <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
            <button class="mg-btn mg-btn-primary" type="submit">Open Training Lab</button>
            <a class="mg-btn mg-btn-ghost" href="/account.php">Back to account</a>
          </form>
          <p class="mg-muted">The signed handoff expires after <?= (int) $config['ttl'] ?> seconds and can be used only once by Training Lab.</p>
        <?php else: ?>
          <div class="mg-account-section">
            <h3>Integration setup is pending</h3>
            <p>The launch button will become available after the shared identity secret and feature flag are configured on the server.</p>
            <a class="mg-btn mg-btn-ghost" href="/account.php">Back to account</a>
          </div>
        <?php endif; ?>

        <div class="mg-account-section">
          <h3>Safety boundaries</h3>
          <p>No payment, wallet, claim, redemption, reward-issuing, or destructive synchronization action occurs during this launch.</p>
        </div>
      </div>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
