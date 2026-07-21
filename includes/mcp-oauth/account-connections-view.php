<header class="mg-ai-hero">
  <div><span class="mg-eyebrow">External agent access · Phase 3A</span><h1>Connect your AI</h1><p>Authorize compatible AI clients to read Microgifter context and, when explicitly approved, prepare reviewable drafts.</p></div>
  <div class="mg-ai-endpoint"><span>MCP server URL</span><code><?= mg_e(mg_mcp_oauth_resource_uri()) ?></code></div>
</header>
<?php if ($notice !== ''): ?><div class="mg-ai-alert is-success"><?= mg_e($notice) ?></div><?php endif; ?>
<?php if ($errorMessage !== ''): ?><div class="mg-ai-alert is-error"><?= mg_e($errorMessage) ?></div><?php endif; ?>
<section class="mg-ai-grid">
  <article><span>ChatGPT</span><h2>Connect through OAuth</h2><p>Add the Microgifter MCP server URL in the client. Microgifter opens for secure account consent.</p><small>Live testing begins after the public MCP host is deployed.</small></article>
  <article><span>Claude</span><h2>Use the same MCP endpoint</h2><p>Compatible remote MCP clients discover authorization from the protected-resource metadata.</p><small>No Microgifter password is shared with the client.</small></article>
  <article><span>Agent drafts</span><h2>Review before anything happens</h2><p>Draft-capable clients can prepare gifts, campaigns, rewards, and messages for your review.</p><small><a href="/account-agent-drafts.php">Open agent drafts →</a></small></article>
</section>
<section class="mg-ai-panel">
  <header><div><span class="mg-eyebrow">Authorized clients</span><h2>Your AI connections</h2></div><span><?= count($connections) ?> total</span></header>
  <?php if ($connections === []): ?>
    <div class="mg-ai-empty"><strong>No external AI clients are connected yet.</strong><p>Begin the connection from your AI client after the public MCP endpoint is deployed.</p></div>
  <?php else: ?>
    <div class="mg-ai-list">
      <?php foreach ($connections as $connection): ?>
        <article><div class="mg-ai-client-mark"><?= mg_e(strtoupper(substr((string)$connection['client']['name'], 0, 2))) ?></div><div class="mg-ai-client-main"><div><strong><?= mg_e((string)$connection['client']['name']) ?></strong><span class="mg-ai-status is-<?= mg_e((string)$connection['status']) ?>"><?= mg_e((string)$connection['status']) ?></span></div><p><?= mg_e((string)$connection['display_name']) ?></p><dl><div><dt>Workspace</dt><dd><?= mg_e((string)$connection['workspace_key']) ?></dd></div><div><dt>Authority</dt><dd><?= mg_e((string)$connection['maximum_operation_class']) ?></dd></div><div><dt>Scopes</dt><dd><?= mg_e(implode(', ', (array)$connection['scopes'])) ?></dd></div><div><dt>Active credentials</dt><dd><?= (int)$connection['active_token_count'] ?></dd></div><div><dt>Last activity</dt><dd><?= mg_e((string)($connection['last_activity_at'] ?? 'Never')) ?></dd></div></dl></div><a class="mg-oauth-button is-secondary" href="/account-ai-connections.php?action=manage&amp;connection=<?= rawurlencode((string)$connection['id']) ?>">Manage</a></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<aside class="mg-ai-safety"><strong>Current authorization boundary</strong><p>Read-only clients cannot create drafts. Draft-capable clients can store reviewable records only. Approval does not publish, send, purchase, schedule, or execute.</p></aside>
