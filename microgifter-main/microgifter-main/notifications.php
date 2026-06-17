<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Notifications | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_styles = ['/assets/css/communications.css'];
$page_scripts = ['/assets/js/notifications-page.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-communications-workspace" data-notifications-page>
  <header class="mg-communications-header">
    <div><span class="mg-eyebrow">Account activity</span><h1>Notifications</h1><p>Gift, claim, delivery, campaign, and account updates.</p></div>
    <a class="mg-btn mg-btn-soft" href="/notification-preferences.php">Notification preferences</a>
  </header>
  <section class="mg-app-panel">
    <div class="mg-communications-toolbar">
      <input type="search" data-notification-search placeholder="Search notifications">
      <select data-notification-category><option value="all">All activity</option><option value="activity">Activity</option><option value="message">Messages</option><option value="operational">Operational</option></select>
      <button class="mg-btn mg-btn-soft" type="button" data-mark-all-read>Mark all read</button>
    </div>
    <div class="mg-notification-list" data-notification-list></div>
  </section>
</section>
<?php require __DIR__ . '/includes/footer.php';
