<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Archived Agents | Microgifter';
$page_section = 'workspace';
$header_mode = 'account';
$page_scripts = ['/assets/js/archived-agents.js'];
require __DIR__ . '/includes/header.php';
$appSidebarVariant = 'utility';
$appSidebarLabel = 'Workspace';
$appSidebarActive = 'agent';
?>
<section class="mg-app-shell mg-utility-app mg-archive-app">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>

  <div class="mg-app-workspace mg-archive-workspace">
    <section class="mg-archive-page" data-archived-agents-page>
      <header class="mg-archive-page-head">
        <div>
          <span class="mg-archive-eyebrow">Agent management</span>
          <h1>Archived agents</h1>
          <p>Archived agents are removed from your active workspace but keep their saved configuration and history.</p>
        </div>
        <a class="mg-btn mg-btn-primary" href="/agent.php">Open agent workspace</a>
      </header>

      <section class="mg-archive-panel">
        <div class="mg-archive-panel-head">
          <div><strong>Archived workspaces</strong><span data-archived-agent-count>0 agents</span></div>
        </div>
        <div class="mg-archive-list" data-archived-agent-list></div>
        <div class="mg-archive-empty" data-archived-agent-empty hidden>
          <strong>No archived agents</strong>
          <p>Agents you archive from the workspace will appear here.</p>
          <a class="mg-btn mg-btn-soft" href="/agent.php">Return to agents</a>
        </div>
      </section>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>