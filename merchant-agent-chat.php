<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Merchant Agent Chat | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-agent-chat.css'];
$page_scripts = ['/assets/js/merchant-agent-chat.js'];
$user = mg_current_user();
$merchantNav = [
  'agent_chat' => ['Agent Chat','Ask the merchant agent','/merchant-agent-chat.php','Agents'],
  'automation' => ['Automation','Guardrails and agent controls','/merchant-automation.php','Agents'],
  'agent_monitor' => ['Agent Monitor','Agent activity and explanations','/merchant-agent-monitor.php','Agents'],
  'agent_approvals' => ['Agent Review','Review and approve agent actions','/merchant-agent-approvals.php','Agents'],
  'agent_execution' => ['Agent Execution','Run reviewed agent actions','/merchant-agent-execution.php','Agents'],
  'agent_messages' => ['Agent Messages','Review agent message drafts','/merchant-agent-messages.php','Agents'],
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