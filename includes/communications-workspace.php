<?php
declare(strict_types=1);
?>
<section class="mg-app-shell mg-agent-app mg-communications-app" data-communications-app>
  <?php require __DIR__ . '/agent-sidebar.php'; ?>
  <div class="mg-app-workspace mg-communications-workspace">
    <section class="mg-communications-header">
      <div>
        <span class="mg-eyebrow">Notifications &amp; messaging</span>
        <h1>Inbox</h1>
        <p>Gift conversations, recipient communication, account activity, campaign updates, and operational alerts.</p>
      </div>
      <button class="mg-btn mg-btn-soft" type="button" data-open-preferences>Notification preferences</button>
    </section>

    <div class="mg-communications-kpis" data-communications-kpis></div>

    <div class="mg-communications-tabs" role="tablist">
      <button class="is-active" type="button" data-communications-tab="messages">Messages</button>
      <button type="button" data-communications-tab="notifications">Notifications</button>
      <button type="button" data-communications-tab="alerts">Operational alerts</button>
    </div>

    <section class="mg-app-panel" data-communications-panel="messages">
      <div class="mg-communications-toolbar"><input type="search" data-message-search placeholder="Search conversations"><button class="mg-btn mg-btn-soft" type="button" data-message-refresh>Refresh</button></div>
      <div class="mg-communications-split">
        <div class="mg-thread-list" data-thread-list></div>
        <section class="mg-thread-detail" data-thread-detail><div class="mg-empty-state"><strong>Select a conversation</strong><p>Messages linked to gifts and PPPM items will appear here.</p></div></section>
      </div>
    </section>

    <section class="mg-app-panel" data-communications-panel="notifications" hidden>
      <div class="mg-communications-toolbar"><input type="search" data-notification-search placeholder="Search notifications"><select data-notification-category><option value="all">All activity</option><option value="activity">Activity</option><option value="message">Messages</option><option value="operational">Operational</option></select><button class="mg-btn mg-btn-soft" type="button" data-mark-all-read>Mark all read</button></div>
      <div class="mg-notification-list" data-notification-list></div>
    </section>

    <section class="mg-app-panel" data-communications-panel="alerts" hidden>
      <div class="mg-alert-list" data-alert-list></div>
    </section>
  </div>
</section>

<div class="mg-communications-modal" data-preferences-modal aria-hidden="true">
  <div class="mg-communications-modal-backdrop" data-close-preferences></div>
  <section class="mg-communications-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="notification-preferences-title">
    <header><div><span>Communication controls</span><h2 id="notification-preferences-title">Notification preferences</h2></div><button type="button" data-close-preferences aria-label="Close">×</button></header>
    <div class="mg-communications-modal-body"><div data-preferences-list></div></div>
  </section>
</div>
