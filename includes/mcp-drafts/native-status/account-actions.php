<?php
declare(strict_types=1);

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        if (!mg_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new MgMcpDraftException('Your session expired. Refresh the page and try again.', 419, 'MCP_NATIVE_STATUS_CSRF_FAILED');
        }
        $action = strtolower(trim((string)($_POST['action'] ?? '')));
        if ($action !== 'refresh_status') {
            throw new MgMcpDraftException('Unknown handoff action.', 422, 'MCP_NATIVE_STATUS_ACTION_INVALID');
        }
        $status = mg_mcp_native_status_for_owner($pdo, $user, (string)($_POST['conversion_id'] ?? ''));
        $notice = $status['observation']['changed']
            ? 'Native draft status refreshed and a new state-change receipt was recorded.'
            : 'Native draft status refreshed. No state change was detected.';
    } catch (MgMcpDraftException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log('error', 'mcp.native_status.owner_refresh_failed', 'Native draft status refresh failed.', [
            'exception_class' => $error::class,
            'exception_message' => mb_substr($error->getMessage(), 0, 500),
        ], (int)$user['id']);
        $errorMessage = 'The native draft status could not be refreshed.';
    }
}
