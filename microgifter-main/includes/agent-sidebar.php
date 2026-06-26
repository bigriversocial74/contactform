<?php
$agentSidebarActive = (string) ($agent_tab ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php'));
$agentSidebarNav = [
  'agent' => ['Agent','/agent.php'],
  'inbox' => ['Inbox','/inbox.php'],
  'sent' => ['Sent','/sent.php'],
  'claimed' => ['Claimed','/claimed.php'],
  'messages' => ['Messages','/messages.php'],
  'merchant' => ['Merchant Workspace','/merchant.php'],
  'build' => ['Create Gift','/build.php'],
];
?>
<aside class="mg-app-sidebar mg-agent-side is-text-sidebar" data-app-sidebar data-sidebar-variant="workspace">
  <div class="mg-app-sidebar-brand mg-agent-sidebar-brand-row">
    <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
  </div>
  <nav class="mg-app-side-nav mg-agent-text-nav" aria-label="Workspace navigation">
    <?php foreach ($agentSidebarNav as $key => $item): ?>
      <a class="<?= $agentSidebarActive === $key ? 'is-active' : '' ?>" href="<?= mg_e($item[1]) ?>"><strong><?= mg_e($item[0]) ?></strong></a>
    <?php endforeach; ?>
  </nav>
</aside>
<div class="mg-merchant-side-actions" hidden aria-hidden="true"><a href="/messages.php">Messages</a><a href="/merchant-locations.php">Locations</a><a href="/merchant-products.php">Products &amp; offers</a><a href="/merchant-pppm.php">Orders &amp; redemptions</a><a href="/merchant-settings.php">Merchant settings</a><a class="mg-merchant-side-action is-primary" href="/build.php">Create gift</a></div>
<div data-scanner-modal data-scanner-api="/api/merchant/scanner-claim.php" hidden aria-hidden="true"></div>
