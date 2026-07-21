<?php
declare(strict_types=1);
require __DIR__ . '/account-connections-controller.php';
$connection = null;
$requested = trim((string)($_GET['connection'] ?? ''));
foreach ($connections as $candidate) {
    if ($requested !== '' && hash_equals((string)$candidate['id'], $requested)) {
        $connection = $candidate;
        break;
    }
}
$page_title = 'Connection Settings | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'ai-connections';
$can_merchant_nav = true;
$page_styles = ['/assets/css/agent-workspace-layout.css','/assets/css/mcp-oauth.css?v=20260720-phase2a'];
require dirname(__DIR__) . '/header.php';
?>
<section class="mg-app-shell">
  <?php require dirname(__DIR__) . '/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-ai-shell">
    <section class="mg-ai-panel">
      <header><h1>Connection settings</h1><a href="/account-ai-connections.php">Back</a></header>
      <?php if ($connection === null): ?><p>Connection unavailable.</p><?php else: ?><?php require __DIR__ . '/account-connection-action.php'; ?><?php endif; ?>
    </section>
  </main>
</section>
<?php require dirname(__DIR__) . '/footer.php'; ?>
