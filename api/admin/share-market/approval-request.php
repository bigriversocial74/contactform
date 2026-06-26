<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/approval-sql-adapter.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.approval.request', 'user:' . (int)$user['id'], 10, 300);

$manifest = is_array($input['manifest'] ?? null) ? $input['manifest'] : [];
$validationToken = trim((string)($input['validation_token'] ?? ''));
$password = (string)($input['password'] ?? '');
$currentBalance = $input['current_balance'] ?? null;
$pdo = mg_db();

try {
    mg_share_market_approval_verify_password($pdo, (int)$user['id'], $password);
    $definition = mg_share_market_approval_verify_manifest($manifest, $user);
    if (!mg_share_market_approval_verify_validation_token($manifest, $validationToken)) {
        throw new DomainException('The validation token is invalid or belongs to another session.');
    }
    $projection = mg_share_market_approval_projection($manifest, $currentBalance);
    $queue = mg_share_market_approval_sql_create_request($pdo, $manifest, $projection, $definition, $user);
    $created = null;
    foreach ($queue['items'] as $item) {
        if ((string)($item['manifest']['payload_hash'] ?? '') === (string)$manifest['payload_hash']) {
            $created = $item;
            break;
        }
    }
    mg_ok(['request' => $created, 'summary' => $queue['summary']], 'Approval request created. No share action was executed.', 201);
} catch (DomainException $e) {
    mg_security_log('warning', 'share_market.approval_request_denied', $e->getMessage(), ['manifest_id' => (string)($manifest['manifest_id'] ?? ''), 'action' => (string)($manifest['action'] ?? '')], (int)$user['id']);
    mg_fail($e->getMessage(), 403);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.approval_request_failed', 'Unable to create Share Market approval request.', ['manifest_id' => (string)($manifest['manifest_id'] ?? ''), 'exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to create the approval request.', 500);
}
