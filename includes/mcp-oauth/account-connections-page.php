<?php
declare(strict_types=1);

require __DIR__ . '/account-connections-controller.php';

$page_title = 'AI Connections | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'ai-connections';
$can_merchant_nav = true;
$page_body_class = 'mg-ai-connections-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/mcp-oauth.css?v=20260720-phase2a',
];

require dirname(__DIR__) . '/header.php';
?>
<section class="mg-app-shell mg-ai-connections-shell">
  <?php require dirname(__DIR__) . '/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-ai-shell">
    <?php require __DIR__ . '/account-connections-view.php'; ?>
  </main>
</section>
<?php require dirname(__DIR__) . '/footer.php'; ?>
