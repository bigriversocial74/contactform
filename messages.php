<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Messages | Microgifter';
$page_section='agent';
$header_mode='agent';
$agent_tab='';
$page_styles=['/assets/css/communications.css','/assets/css/messages-source-metadata.css','/assets/css/message-delivery-proof.css'];
$page_scripts=['/assets/js/messages-center.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-app mg-communications-app" data-messages-center>
<?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
<div class="mg-app-workspace mg-communications-workspace">
<header class="mg-communications-header"><div><span class="mg-eyebrow">Gift communication</span><h1>Messages</h1><p>Conversations connected to gifts, recipients, merchants, agents, Store Canvas sessions, Merchant CRM, and PPPM items.</p></div><div class="mg-gift-center-header-actions"><a class="mg-btn mg-btn-soft" href="/inbox.php">Gift Inbox</a><a class="mg-btn mg-btn-soft" href="/notification-preferences.php">Notification preferences</a></div></header>
<div class="mg-communications-kpis" data-message-kpis></div>
<section class="mg-app-panel"><div class="mg-communications-toolbar"><input type="search" data-message-search placeholder="Search conversations"><button class="mg-btn mg-btn-soft" type="button" data-message-refresh>Refresh</button></div><div class="mg-communications-split"><div class="mg-thread-list" data-thread-list></div><section class="mg-thread-detail" data-thread-detail><div class="mg-empty-state"><strong>Select a conversation</strong><p>Loaded gift, Store Canvas, Merchant CRM, and recipient conversations will appear here.</p></div></section></div></section>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>