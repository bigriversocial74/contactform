<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/mcp-oauth.php';

mg_apply_page_security_headers();
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

$pdo = mg_db();
$errorMessage = '';
$request = null;
$user = null;
$workspaces = [];

try {
    mg_mcp_oauth_require_enabled();
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        $user = mg_require_auth('/signin.php', (string)($_SERVER['REQUEST_URI'] ?? '/oauth/authorize.php'));
        if (!mg_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new MgMcpOAuthException('Your authorization session expired. Return to the AI client and try again.', 'invalid_request', 419);
        }
        $result = mg_mcp_oauth_authorization_decision(
            $pdo,
            $user,
            trim((string)($_POST['request_id'] ?? '')),
            trim((string)($_POST['decision'] ?? 'deny')),
            $_POST['workspace_public_id'] ?? ''
        );
        header('Location: ' . mg_mcp_oauth_redirect_location($result['redirect_uri'], $result['parameters']), true, 302);
        exit;
    }

    $validated = mg_mcp_oauth_validate_authorization_input($pdo, $_GET);
    $user = mg_require_auth('/signin.php', (string)($_SERVER['REQUEST_URI'] ?? '/oauth/authorize.php'));
    $request = mg_mcp_oauth_create_authorization_request($pdo, $validated);
    $workspaces = mg_mcp_oauth_user_workspaces($pdo, (int)$user['id']);
} catch (MgMcpOAuthException $error) {
    $errorMessage = $error->getMessage();
} catch (Throwable $error) {
    mg_security_log('error', 'mcp.oauth_authorize.failed', 'MCP OAuth authorization page failed.', [
        'exception_class' => $error::class,
        'exception_message' => mb_substr($error->getMessage(), 0, 500),
    ], isset($user['id']) ? (int)$user['id'] : null);
    $errorMessage = 'The authorization request could not be completed.';
}

$page_title = 'Authorize AI Connection | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-mcp-oauth-page';
$page_styles = ['/assets/css/mcp-oauth.css?v=20260720-phase2a'];
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="mg-oauth-shell">
  <section class="mg-oauth-card">
    <?php if ($errorMessage !== '' || !$request): ?>
      <div class="mg-oauth-icon is-error" aria-hidden="true">!</div>
      <span class="mg-eyebrow">Connection unavailable</span>
      <h1>We could not authorize this AI client</h1>
      <p><?= mg_e($errorMessage !== '' ? $errorMessage : 'The authorization request is invalid or expired.') ?></p>
      <a class="mg-oauth-button is-secondary" href="/account-ai-connections.php">Open AI connections</a>
    <?php else: ?>
      <div class="mg-oauth-icon" aria-hidden="true">AI</div>
      <span class="mg-eyebrow">External AI authorization</span>
      <h1>Connect <?= mg_e((string)$request['client_name']) ?>?</h1>
      <p class="mg-oauth-lead">
        <?= mg_e((string)$request['client_name']) ?> is asking to use Microgifter on your behalf.
        The connection remains read-only in Phase 2A and can be revoked at any time.
      </p>

      <div class="mg-oauth-identity">
        <span>Signed in as</span>
        <strong><?= mg_e((string)($user['display_name'] ?? $user['full_name'] ?? $user['email'] ?? 'Microgifter user')) ?></strong>
        <small><?= mg_e((string)($user['email'] ?? '')) ?></small>
      </div>

      <form method="post" class="mg-oauth-form">
        <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
        <input type="hidden" name="request_id" value="<?= mg_e((string)$request['public_id']) ?>">

        <fieldset>
          <legend>Choose the authorized workspace</legend>
          <label class="mg-oauth-choice">
            <input type="radio" name="workspace_public_id" value="" checked>
            <span><strong>My Microgifter account</strong><small>Account-level catalog and profile access</small></span>
          </label>
          <?php foreach ($workspaces as $workspace): ?>
            <label class="mg-oauth-choice">
              <input type="radio" name="workspace_public_id" value="<?= mg_e((string)$workspace['public_id']) ?>">
              <span><strong><?= mg_e((string)$workspace['name']) ?></strong><small>Merchant workspace</small></span>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <section class="mg-oauth-permissions">
          <h2>Requested permissions</h2>
          <?php foreach ((array)$request['scopes'] as $scope): ?>
            <div>
              <span aria-hidden="true">✓</span>
              <p><strong><?= mg_e((string)$scope) ?></strong>
                <small><?= $scope === 'profile:read'
                    ? 'Read the authorized account and workspace context.'
                    : 'Search and view published Microgifter catalog products.' ?></small>
              </p>
            </div>
          <?php endforeach; ?>
        </section>

        <div class="mg-oauth-boundary">
          <strong>Phase 2A safety boundary</strong>
          <p>No purchases, messages, campaigns, rewards, gifts, or other write actions are authorized by this connection.</p>
        </div>

        <div class="mg-oauth-actions">
          <button class="mg-oauth-button is-secondary" type="submit" name="decision" value="deny">Cancel</button>
          <button class="mg-oauth-button" type="submit" name="decision" value="approve">Authorize connection</button>
        </div>
      </form>
    <?php endif; ?>
  </section>
</main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
