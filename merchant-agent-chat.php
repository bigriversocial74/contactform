<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Merchant Agent Chat | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-agent-chat.css','/assets/css/merchant-agent-chat-followup.css','/assets/css/merchant-agent-chat-skills.css'];
$page_scripts = ['/assets/js/merchant-agent-chat.js','/assets/js/merchant-agent-chat-json-format.js'];
$page_scripts[] = '/assets/js/merchant-agent-chat-admin-mode.js';
$user = mg_current_user();
$merchantNav = [
  'agent_chat' => ['Agent Chat','Merchant agent conversation','/merchant-agent-chat.php','Agents'],
  'automation' => ['Automation','Agent controls','/merchant-automation.php','Agents'],
  'agent_monitor' => ['Agent Monitor','Agent activity','/merchant-agent-monitor.php','Agents'],
  'agent_approvals' => ['Agent Review','Review queue','/merchant-agent-approvals.php','Agents'],
  'agent_execution' => ['Agent Execution','Approved action queue','/merchant-agent-execution.php','Agents'],
  'agent_messages' => ['Agent Messages','Message drafts','/merchant-agent-messages.php','Agents'],
];
$appSidebarNav = [];
foreach ($merchantNav as $key => $item) {
    $appSidebarNav[$key] = ['section' => $item[3] ?? '', 'label' => $item[0], 'detail' => $item[1], 'href' => $item[2], 'visible' => true, 'active' => $key === 'agent_chat'];
}
$appSidebarVariant = 'merchant';
$appSidebarLabel = 'Agents';
$appSidebarActive = 'agent_chat';
$appSidebarCompact = true;
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app mg-agent-chat-app" data-merchant-app data-merchant-view="agent_chat" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <?php if (!$user): ?>
      <section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Merchant access</h2><p>Sign in to use merchant agent chat.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section>
    <?php else: ?>
      <?php require __DIR__ . '/includes/merchant-agent-chat-view.php'; ?>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>