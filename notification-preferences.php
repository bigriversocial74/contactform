<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Notification Preferences | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'preferences';
$page_styles = ['/assets/css/agent-workspace-layout.css','/assets/css/account-commerce.css','/assets/css/account-commerce-fixes.css','/assets/css/communications.css','/assets/css/notification-preferences.css','/assets/css/recipient-notifications.css'];
$page_scripts = ['/assets/js/notification-preferences.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-notifications-page" data-notification-preferences>
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-shell mg-preferences-page">
    <header class="mg-preferences-hero">
      <div><span class="mg-eyebrow">Communication controls</span><h1>Notification preferences</h1><p>Choose how and when Microgifter contacts you about followers, messages, gifts, claims, deliveries, and account activity.</p></div>
      <a class="mg-btn mg-btn-soft" href="/notifications.php">Back to notifications</a>
    </header>
    <section class="mg-preferences-card">
      <div class="mg-preferences-grid-head"><span>Notification type</span><span>In app</span><span>Email</span><span>SMS</span><span>Push</span><span>Delivery</span><span></span></div>
      <div data-preferences-list><div class="mg-empty-state"><strong>Loading preferences…</strong></div></div>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
