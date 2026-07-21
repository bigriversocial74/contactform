<?php
$requestedConnectionId = trim((string)($_GET['connection'] ?? ''));
$selectedConnection = null;
foreach ($connections as $candidateConnection) {
    if ($requestedConnectionId !== '' && hash_equals((string)$candidateConnection['id'], $requestedConnectionId)) {
        $selectedConnection = $candidateConnection;
        break;
    }
}
?>
<?php if ($selectedConnection !== null): ?>
<section class="mg-ai-panel mg-ai-manage-panel">
  <header><div><span class="mg-eyebrow">Connection settings</span><h2><?= mg_e((string)$selectedConnection['client']['name']) ?></h2></div><a href="/account-ai-connections.php">Close</a></header>
  <p>Disconnect this client to end its access to your Microgifter account and workspace.</p>
  <?php $connection = $selectedConnection; require __DIR__ . '/account-connection-action.php'; ?>
</section>
<?php endif; ?>
