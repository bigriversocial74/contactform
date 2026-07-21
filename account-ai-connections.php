<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/mcp-oauth.php';

$user = mg_require_auth();
$pdo = mg_db();
$notice = '';
$errorMessage = '';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        if (!mg_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new MgMcpOAuthException('Your session expired. Refresh the page and try again.', 'invalid_request', 419);
        }
        mg_mcp_oauth_revoke_user_connection(
            $pdo,
            (int)$user['id'],
            trim((string)($_POST['connection_id'] ?? '')),
            trim((string)($_POST['reason'] ?? 'User revoked external AI access.'))
        );
        header('Location: /account-ai-connections.php?revoked=1', true, 303);
        exit;
    } catch (MgMcpOAuthException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log('error', 'mcp.oauth_account_action.failed', 'AI connection account action failed.', [
            'exception_class' => $error::class,
            'exception_message' => mb_substr($error->getMessage(), 0, 500),
        ], (int)$user['id']);
        $errorMessage = 'The AI connection could not be updated.';
    }
}

if (isset($_GET['revoked'])) {
    $notice = 'The external AI connection and its active token family were revoked.';
}
$connections = mg_mcp_oauth_user_connections($pdo, (int)$user['id']);

$page_title = 'AI Connections | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-ai-connections-page';
$page_styles = ['/assets/css/mcp-oauth.css?v=20260720-phase2a'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-ai-shell">
  <header class="mg-ai-hero">
    <div>
      <span class="mg-eyebrow">External agent access · Phase 2A</span>
      <h1>Connect your AI</h1>
      <p>Authorize compatible AI clients to read your Microgifter account context and published catalog through the MCP server.</p>
    </div>
    <div class="mg-ai-endpoint">
      <span>MCP server URL</span>
      <code><?= mg_e(mg_mcp_oauth_resource_uri()) ?></code>
    </div>
  </header>

  <?php if ($notice !== ''): ?><div class="mg-ai-alert is-success"><?= mg_e($notice) ?></div><?php endif; ?>
  <?php if ($errorMessage !== ''): ?><div class="mg-ai-alert is-error"><?= mg_e($errorMessage) ?></div><?php endif; ?>

  <section class="mg-ai-grid">
    <article><span>ChatGPT</span><h2>Connect through OAuth</h2><p>Add the Microgifter MCP server URL in the client. ChatGPT will open Microgifter for secure account consent.</p><small>Live connection testing begins after <code>mcp.microgifter.com</code> is deployed.</small></article>
    <article><span>Claude</span><h2>Use the same MCP endpoint</h2><p>Claude-compatible remote MCP clients discover authorization automatically from the protected-resource metadata.</p><small>No Microgifter password is shared with the AI client.</small></article>
    <article><span>Custom harnesses</span><h2>OAuth 2.1 + PKCE</h2><p>Clients can use dynamic registration when enabled, exact redirect URIs, resource indicators, and rotating refresh tokens.</p><small>Phase 2A remains read-only.</small></article>
  </section>

  <section class="mg-ai-panel">
    <header><div><span class="mg-eyebrow">Authorized clients</span><h2>Your AI connections</h2></div><span><?= count($connections) ?> total</span></header>
    <?php if ($connections === []): ?>
      <div class="mg-ai-empty"><strong>No external AI clients are connected yet.</strong><p>Once the public MCP endpoint is deployed, begin the connection from your AI client using the server URL above.</p></div>
    <?php else: ?>
      <div class="mg-ai-list">
        <?php foreach ($connections as $connection): ?>
          <article>
            <div class="mg-ai-client-mark"><?= mg_e(strtoupper(substr((string)$connection['client']['name'], 0, 2))) ?></div>
            <div class="mg-ai-client-main">
              <div><strong><?= mg_e((string)$connection['client']['name']) ?></strong><span class="mg-ai-status is-<?= mg_e((string)$connection['status']) ?>"><?= mg_e((string)$connection['status']) ?></span></div>
              <p><?= mg_e((string)$connection['display_name']) ?></p>
              <dl>
                <div><dt>Workspace</dt><dd><?= mg_e((string)$connection['workspace_key']) ?></dd></div>
                <div><dt>Scopes</dt><dd><?= mg_e(implode(', ', (array)$connection['scopes'])) ?></dd></div>
                <div><dt>Active tokens</dt><dd><?= (int)$connection['active_token_count'] ?></dd></div>
                <div><dt>Last activity</dt><dd><?= mg_e((string)($connection['last_activity_at'] ?? 'Never')) ?></dd></div>
              </dl>
            </div>
            <?php if ((string)$connection['status'] !== 'revoked'): ?>
              <form method="post" onsubmit="return confirm('Revoke this AI connection and all active tokens?');">
                <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
                <input type="hidden" name="connection_id" value="<?= mg_e((string)$connection['id']) ?>">
                <input type="hidden" name="reason" value="User revoked access from AI Connections.">
                <button class="mg-oauth-button is-secondary" type="submit">Revoke</button>
              </form>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <aside class="mg-ai-safety"><strong>Current authorization boundary</strong><p>External clients may read account context and published catalog data only. Write-capable tools, schedulers, autonomous purchases, messaging, campaign changes, and reward actions remain disabled.</p></aside>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
