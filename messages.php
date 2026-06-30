<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Messages | Microgifter';
$page_section='agent';
$header_mode='agent';
$agent_tab='messages';
$page_styles=['/assets/css/communications.css','/assets/css/messages-source-metadata.css','/assets/css/message-delivery-proof.css','/assets/css/messages-redesign.css','/assets/css/messages-composer-compact.css'];
$page_scripts=['/assets/js/messages-center.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-app mg-communications-app mg-messages-layout" data-messages-center>
  <div class="mg-messages-sidebar-backdrop" data-messages-sidebar-backdrop hidden></div>
  <aside id="mg-messages-sidebar" class="mg-app-sidebar mg-universal-sidebar mg-messages-sidebar is-text-sidebar" data-app-sidebar data-sidebar-variant="messages" aria-label="Message conversations">
    <div class="mg-app-sidebar-brand mg-universal-sidebar-brand">
      <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
    </div>
    <div class="mg-messages-sidebar-head">
      <div>
        <span class="mg-eyebrow">Gift communication</span>
        <h1>Messages</h1>
      </div>
      <button class="mg-message-compose-trigger" type="button" data-message-refresh aria-label="Refresh conversations">↻</button>
    </div>
    <div class="mg-messages-sidebar-search">
      <input type="search" data-message-search placeholder="Search conversations" aria-label="Search conversations">
    </div>
    <div class="mg-messages-sidebar-filters" aria-label="Conversation filters">
      <button type="button" class="is-active" data-message-filter="all">All</button>
      <button type="button" data-message-filter="open">Open</button>
      <button type="button" data-message-filter="unread">Unread</button>
    </div>
    <div class="mg-messages-sidebar-meta" data-message-kpis></div>
    <div class="mg-thread-list mg-messages-thread-list" data-thread-list></div>
    <div class="mg-messages-sidebar-actions">
      <a href="/inbox.php">Gift Inbox</a>
      <a href="/notification-preferences.php">Notification Preferences</a>
    </div>
  </aside>
  <div class="mg-app-workspace mg-communications-workspace mg-messages-workspace">
    <section class="mg-app-panel mg-messages-panel">
      <section class="mg-thread-detail" data-thread-detail>
        <div class="mg-empty-state mg-messages-empty-state">
          <strong>Select a conversation</strong>
          <p>Merchant CRM, Store Canvas, gift, recipient, and PPPM conversations will appear here.</p>
        </div>
      </section>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
