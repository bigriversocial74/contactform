<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Merchant Agent Chat | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$agent_tab = 'agent_chat';
$page_body_class = 'mg-merchant-agent-chat-page';
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
<style id="mg-agent-chat-canvas-layout-v2-3">
body.mg-merchant-agent-chat-page{
  overflow:hidden!important;
}
.mg-agent-chat-layout-v2 .mg-agent-chat-main-stack{
  grid-template-rows:minmax(0,1fr) auto auto!important;
  position:relative!important;
  height:100%!important;
  min-height:0!important;
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
  -webkit-overflow-scrolling:touch!important;
}
.mg-agent-chat-layout-v2 .mg-agent-chat-main-stack>.mg-form-status{
  grid-row:2!important;
}
.mg-agent-chat-layout-v2 .mg-agent-chat-main-stack>.mg-form-status:empty{
  display:none!important;
}
.mg-agent-chat-layout-v2 .mg-agent-chat-composer-shell{
  grid-row:3!important;
  align-self:end!important;
  position:sticky!important;
  top:auto!important;
  bottom:0!important;
  z-index:60!important;
  margin:8px 0 0!important;
  flex:0 0 auto!important;
  background:rgba(255,255,255,.99)!important;
  box-shadow:0 -14px 34px rgba(15,23,42,.09),0 10px 28px rgba(15,23,42,.08)!important;
}
.mg-agent-chat-layout-v2 .mg-agent-chat-prompts{
  display:grid!important;
  grid-template-columns:repeat(2,minmax(0,1fr))!important;
  width:min(640px,100%)!important;
  margin:18px auto 0!important;
  gap:8px!important;
}
@media(max-width:980px){
  html body.mg-app-page.mg-merchant-agent-chat-page{
    --mg-app-header:var(--mg-mobile-topbar,72px);
    --mg-mobile-shell-offset:var(--mg-mobile-topbar,72px);
    height:100dvh!important;
    min-height:100dvh!important;
    overflow:hidden!important;
  }
  html body.mg-app-page.mg-merchant-agent-chat-page .mg-main{
    box-sizing:border-box!important;
    height:100dvh!important;
    min-height:100dvh!important;
    padding-top:0!important;
    padding-left:0!important;
    margin-top:0!important;
    overflow:hidden!important;
  }
  html body.mg-app-page.mg-merchant-agent-chat-page .mg-app-shell.mg-agent-chat-layout-v2{
    box-sizing:border-box!important;
    height:100dvh!important;
    min-height:100dvh!important;
    padding-top:var(--mg-mobile-topbar,72px)!important;
    padding-left:0!important;
    margin-top:0!important;
    overflow:hidden!important;
  }
  html body.mg-app-page.mg-merchant-agent-chat-page .mg-agent-chat-layout-v2 .mg-merchant-main{
    box-sizing:border-box!important;
    height:calc(100dvh - var(--mg-mobile-topbar,72px))!important;
    min-height:0!important;
    padding:0 8px max(8px,env(safe-area-inset-bottom))!important;
    overflow:hidden!important;
  }
  .mg-agent-chat-layout-v2 .mg-agent-chat-page{
    height:100%!important;
    min-height:0!important;
    grid-template-rows:auto minmax(0,1fr)!important;
    gap:6px!important;
    overflow:hidden!important;
  }
  .mg-agent-chat-layout-v2 .mg-agent-chat-mobile-controls{
    position:relative!important;
    top:auto!important;
    z-index:70!important;
    margin:0!important;
    flex:0 0 auto!important;
  }
  .mg-agent-chat-layout-v2 .mg-agent-chat-layout{
    height:100%!important;
    min-height:0!important;
    overflow:hidden!important;
  }
  .mg-agent-chat-layout-v2 .mg-agent-chat-main-stack{
    height:100%!important;
    min-height:0!important;
    grid-template-rows:minmax(0,1fr) auto auto!important;
    overflow:hidden!important;
  }
  .mg-agent-chat-layout-v2 .mg-agent-chat-main{
    height:100%!important;
    min-height:0!important;
    overflow:hidden!important;
  }
  .mg-agent-chat-layout-v2 .mg-agent-chat-feed{
    height:100%!important;
    min-height:0!important;
    overflow-y:auto!important;
    padding-bottom:20px!important;
  }
  .mg-agent-chat-layout-v2 .mg-agent-chat-composer-shell{
    position:sticky!important;
    top:auto!important;
    bottom:0!important;
    z-index:80!important;
    margin:7px 0 0!important;
    flex:0 0 auto!important;
  }
}
@media(max-width:640px){
  .mg-agent-chat-layout-v2 .mg-agent-chat-prompts{
    grid-template-columns:minmax(0,1fr)!important;
    margin-top:14px!important;
  }
}
@media(max-width:430px){
  html body.mg-app-page.mg-merchant-agent-chat-page .mg-agent-chat-layout-v2 .mg-merchant-main{
    padding:0 5px max(5px,env(safe-area-inset-bottom))!important;
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
