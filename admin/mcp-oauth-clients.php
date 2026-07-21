<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/mcp-oauth.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.settings.manage');
$pdo = mg_db();
$notice = '';
$errorMessage = '';
$created = null;

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        if (!mg_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new MgMcpOAuthException('Your admin session expired. Refresh and try again.', 'invalid_request', 419);
        }
        $redirectUris = preg_split('/\R+/', trim((string)($_POST['redirect_uris'] ?? ''))) ?: [];
        $created = mg_mcp_oauth_register_client($pdo, [
            'client_name' => $_POST['client_name'] ?? '',
            'client_type' => $_POST['client_type'] ?? 'custom',
            'client_uri' => $_POST['client_uri'] ?? '',
            'logo_uri' => $_POST['logo_uri'] ?? '',
            'redirect_uris' => array_values(array_filter(array_map('trim', $redirectUris))),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ], (int)$user['id'], 'preregistered');
        $notice = 'OAuth client registered. Copy the registration access token now; only its hash is stored.';
    } catch (MgMcpOAuthException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log('error', 'admin.mcp_oauth_client.failed', 'Admin OAuth client registration failed.', [
            'exception_class' => $error::class,
            'exception_message' => mb_substr($error->getMessage(), 0, 500),
        ], (int)$user['id']);
        $errorMessage = 'The OAuth client could not be registered.';
    }
}

$stmt = $pdo->query(
    "SELECT r.client_id,r.client_name,r.status,r.registration_type,r.redirect_uris_json,r.client_uri,
            r.last_used_at,r.created_at,c.client_type
     FROM mcp_oauth_client_registrations r
     INNER JOIN mcp_clients c ON c.id=r.mcp_client_id
     ORDER BY r.id DESC LIMIT 100"
);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'MCP OAuth Clients | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-mcp-oauth-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/mcp-oauth.css?v=20260720-phase2a'];
$adminActive = 'mcp-oauth-clients';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-admin-oauth-shell">
      <header class="mg-ai-hero">
        <div><a href="/admin/mcp-connections.php">← MCP operations</a><span class="mg-eyebrow">MCP Phase 2A</span><h1>OAuth clients</h1><p>Pre-register external AI clients and exact callback URLs. Dynamic registration remains separately controlled by environment configuration.</p></div>
        <div class="mg-ai-endpoint"><span>Authorization issuer</span><code><?= mg_e(mg_mcp_oauth_issuer()) ?></code></div>
      </header>
      <?php if ($notice !== ''): ?><div class="mg-ai-alert is-success"><?= mg_e($notice) ?></div><?php endif; ?>
      <?php if ($errorMessage !== ''): ?><div class="mg-ai-alert is-error"><?= mg_e($errorMessage) ?></div><?php endif; ?>
      <?php if (is_array($created)): ?>
        <section class="mg-ai-secret"><strong>One-time registration bundle</strong><label>Client ID<input readonly value="<?= mg_e((string)$created['client_id']) ?>"></label><label>Registration access token<input readonly value="<?= mg_e((string)$created['registration_access_token']) ?>"></label><p>Do not store this token in GitHub, JavaScript, or the database.</p></section>
      <?php endif; ?>
      <div class="mg-admin-oauth-grid">
        <section class="mg-ai-panel">
          <header><div><span class="mg-eyebrow">Pre-registration</span><h2>Add OAuth client</h2></div></header>
          <form method="post" class="mg-admin-oauth-form">
            <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
            <label>Client name<input required name="client_name" maxlength="180" placeholder="ChatGPT"></label>
            <label>Client type<select name="client_type"><option value="chatgpt">ChatGPT</option><option value="claude">Claude</option><option value="custom" selected>Custom</option><option value="enterprise">Enterprise</option></select></label>
            <label>Client website<input name="client_uri" type="url" maxlength="500" placeholder="https://example.com"></label>
            <label>Logo URL<input name="logo_uri" type="url" maxlength="500" placeholder="https://example.com/logo.png"></label>
            <label>Exact redirect URIs<textarea required name="redirect_uris" rows="6" placeholder="https://client.example.com/oauth/callback&#10;http://127.0.0.1:3000/callback"></textarea><small>One URI per line. HTTPS is required except for localhost loopback callbacks.</small></label>
            <button class="mg-oauth-button" type="submit">Register client</button>
          </form>
        </section>
        <section class="mg-ai-panel">
          <header><div><span class="mg-eyebrow">Registry</span><h2>External clients</h2></div><span><?= count($clients) ?> total</span></header>
          <div class="mg-admin-oauth-list">
            <?php foreach ($clients as $client): ?>
              <article><div><strong><?= mg_e((string)$client['client_name']) ?></strong><span><?= mg_e((string)$client['status']) ?></span></div><code><?= mg_e((string)$client['client_id']) ?></code><p><?= mg_e(implode("\n", mg_mcp_oauth_json_decode($client['redirect_uris_json']))) ?></p><small><?= mg_e((string)$client['registration_type']) ?> · <?= mg_e((string)$client['client_type']) ?> · Last used <?= mg_e((string)($client['last_used_at'] ?? 'never')) ?></small></article>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
