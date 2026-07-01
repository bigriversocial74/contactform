<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Merchant Agent Chat | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$agent_tab = 'agent_chat';
$page_styles = [
    '/assets/css/merchant-workspace.css',
    '/assets/css/merchant-agent-chat.css',
    '/assets/css/merchant-agent-chat-followup.css',
    '/assets/css/merchant-agent-chat-skills.css',
    '/assets/css/merchant-agent-chat-mobile.css',
    '/assets/css/merchant-agent-chat-cleanup.css',
    '/assets/css/merchant-agent-chat-flat-layout.css',
    '/assets/css/merchant-agent-chat-desktop.css',
    '/assets/css/merchant-agent-chat-mobile-offset.css',
    '/assets/css/merchant-agent-chat-voice.css',
    '/assets/css/sponsored-campaign-card.css',
];
$page_scripts = [
    '/assets/js/merchant-agent-chat.js',
    '/assets/js/merchant-agent-chat-json-format.js',
    '/assets/js/merchant-agent-chat-mobile.js',
    '/assets/js/sponsored-campaign-card.js',
];
$page_scripts[] = '/assets/js/merchant-agent-chat-admin-mode.js';
$user = mg_current_user();
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app mg-agent-chat-app mg-agent-chat-app-no-nav" data-merchant-app data-merchant-view="agent_chat" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <?php if (!$user): ?>
      <section class="mg-app-panel">
        <div class="mg-app-panel-head"><div><h2>Merchant access</h2><p>Sign in to use merchant agent chat.</p></div></div>
        <div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div>
      </section>
    <?php else: ?>
      <?php require __DIR__ . '/includes/merchant-agent-chat-view.php'; ?>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
