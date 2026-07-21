<?php
declare(strict_types=1);

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
    $notice = 'The external AI connection was revoked.';
}
$connections = mg_mcp_oauth_user_connections($pdo, (int)$user['id']);
