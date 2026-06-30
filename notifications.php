<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Notifications | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'notifications';
$page_styles = ['/assets/css/agent-workspace-layout.css','/assets/css/account-commerce.css','/assets/css/account-commerce-fixes.css','/assets/css/communications.css','/assets/css/recipient-notifications.css','/assets/css/message-delivery-proof.css','/assets/css/pwa-notifications.css'];
$page_scripts = ['/assets/js/notifications-page.js','/assets/js/notifications-source-metadata.js','/assets/js/pwa-notifications.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-notifications-page">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-shell">
    <section class="mg-communications-workspace" data-notifications-page>
      <header class="mg-communications-header">
        <div><span class="mg-eyebrow">Account activity</span><h1>Notifications</h1><p>Follow, message, gift, claim, delivery, and account updates that involve you.</p></div>
        <a class="mg-btn mg-btn-soft" href="/notification-preferences.php">Notification preferences</a>
      </header>

      <section class="mg-pwa-notification-panel" data-pwa-notification-panel aria-live="polite">
        <header>
          <div>
            <span class="mg-eyebrow">PWA delivery channel</span>
            <h2 data-pwa-status-text>Checking PWA notifications</h2>
            <p data-pwa-detail-text>Browser push support stays permission-gated and only works for authenticated Microgifter users.</p>
            <div class="mg-pwa-status-grid" data-pwa-status-grid></div>
          </div>
          <div class="mg-pwa-actions">
            <button class="mg-btn mg-btn-primary" type="button" data-pwa-enable disabled>Enable PWA notifications</button>
            <button class="mg-btn mg-btn-soft" type="button" data-pwa-disable disabled>Disable on this browser</button>
          </div>
        </header>
        <p>Push payloads stay minimal: title, body, notification type, safe action URL, icon/badge, and non-sensitive metadata only. Full gift, claim, merchant, or admin details load after authentication.</p>
      </section>

      <section class="mg-app-panel">
        <div class="mg-communications-toolbar">
          <input type="search" data-notification-search placeholder="Search notifications">
          <select data-notification-category><option value="all">All activity</option><option value="activity">Gifts and social</option><option value="message">Messages</option><option value="operational">Operational</option></select>
          <button class="mg-btn mg-btn-soft" type="button" data-mark-all-read>Mark all read</button>
        </div>
        <div class="mg-notification-list" data-notification-list></div>
      </section>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
