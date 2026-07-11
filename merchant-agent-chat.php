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
    '/assets/css/merchant-agent-chat-voice.css',
    '/assets/css/merchant-agent-chat-control-panel.css',
    '/assets/css/merchant-agent-memory-menu.css',
    '/assets/css/merchant-agent-creative-draft-actions.css',
    '/assets/css/merchant-agent-chat-recipe-cards.css',
    '/assets/css/sponsored-campaign-card.css',
    '/assets/css/merchant-agent-chat-layout-v2.css?v=2.1.0',
];
$page_scripts = [
    '/assets/js/merchant-agent-chat.js',
    '/assets/js/merchant-agent-chat-voice.js',
    '/assets/js/merchant-agent-chat-speech-results.js',
    '/assets/js/merchant-agent-chat-control-panel.js',
    '/assets/js/merchant-agent-memory-menu.js',
    '/assets/js/merchant-agent-creative-draft-actions.js',
    '/assets/js/merchant-agent-chat-scroll-latest.js',
    '/assets/js/merchant-agent-chat-json-format.js',
    '/assets/js/merchant-agent-chat-mobile.js?v=2.0.0',
    '/assets/js/sponsored-campaign-card.js',
];
$page_scripts[] = '/assets/js/merchant-agent-chat-admin-mode.js';
$user = mg_current_user();
require __DIR__ . '/includes/header.php';
?>
<style id="mg-agent-chat-canvas-layout-v2-2">
.mg-agent-chat-layout-v2 .mg-agent-chat-main-stack{
  grid-template-rows:minmax(0,1fr) auto auto!important;
  position:relative!important;
  overflow:hidden!important;
}
.mg-agent-chat-layout-v2 .mg-agent-chat-main{
  grid-row:1!important;
  min-height:0!important;
  overflow:hidden!important;
}
.mg-agent-chat-layout-v2 .mg-agent-chat-feed{
  min-height:0!important;
  overflow-x:hidden!important;
  overflow-y:auto!important;
  overscroll-behavior:contain!important;
}
.mg-agent-chat-layout-v2 .mg-agent-chat-main-stack>.mg-form-status{
  grid-row:2!important;
}
.mg-agent-chat-layout-v2 .mg-agent-chat-composer-shell{
  grid-row:3!important;
  align-self:end!important;
  position:sticky!important;
  bottom:0!important;
  margin-top:8px!important;
}
.mg-agent-chat-layout-v2 .mg-agent-chat-prompts{
  display:grid!important;
  grid-template-columns:repeat(2,minmax(0,1fr))!important;
  width:min(640px,100%)!important;
  margin:18px auto 0!important;
  gap:8px!important;
}
@media(max-width:640px){
  .mg-agent-chat-layout-v2 .mg-agent-chat-prompts{
    grid-template-columns:minmax(0,1fr)!important;
    margin-top:14px!important;
  }
}
</style>
<section class="mg-app-shell mg-merchant-app mg-agent-chat-app mg-agent-chat-layout-v2" data-merchant-app data-merchant-view="agent_chat" data-sidebar-contract="mg-app-sidebar">
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
