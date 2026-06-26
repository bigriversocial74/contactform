<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/approval-workflow.php';

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
$lockName = 'sm-approval-manifest:' . substr(hash('sha256', (string)($manifest['manifest_id'] ?? '')), 0, 32);
$lockAcquired = false;

try {
    mg_share_market_approval_verify_password($pdo, (int)$user['id'], $password);
    $definition = mg_share_market_approval_verify_manifest($manifest, $user);
    if (!mg_share_market_approval_verify_validation_token($manifest, $validationToken)) {
        throw new DomainException('The validation token is invalid or belongs to another session.');
    }
    $projection = mg_share_market_approval_projection($manifest, $currentBalance);

    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?,5)');
    $lockStmt->execute([$lockName]);
    $lockAcquired = (int)$lockStmt->fetchColumn() === 1;
    if (!$lockAcquired) throw new RuntimeException('The approval queue is busy. Try again.');

    $pdo->beginTransaction();
    $duplicate = $pdo->prepare("SELECT id FROM events WHERE event_type='share_market.approval.requested' AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.manifest.manifest_id'))=? LIMIT 1 FOR UPDATE");
    $duplicate->execute([(string)$manifest['manifest_id']]);
    if ($duplicate->fetchColumn()) throw new DomainException('This validated manifest is already in the approval queue.');

    $requestId = mg_share_market_admin_manifest_id();
    $createdAt = gmdate('c');
    $expiresAt = gmdate('c', time() + 86400);
    $payload = [
        'request_id' => $requestId,
        'manifest' => $manifest,
        'projection' => $projection,
        'requester_user_id' => (int)$user['id'],
        'required_approvals' => (int)$definition['required_approvals'],
        'status' => 'awaiting_first_approval',
        'note' => 'Validated Share Market action submitted for approval.',
        'created_at' => $createdAt,
        'expires_at' => $expiresAt,
        'execution_enabled' => false,
        'storage_mode' => 'events_compatibility',
    ];
    mg_share_market_approval_append_event($pdo, 'share_market.approval.requested', $payload, (int)$user['id']);
    mg_audit('share_market.approval.requested', 'share_market_approval', [
        'request_id' => $requestId,
        'action' => (string)$manifest['action'],
        'target_type' => (string)$manifest['target_type'],
        'target_id' => (string)$manifest['target_id'],
        'payload_hash' => (string)$manifest['payload_hash'],
        'required_approvals' => (int)$definition['required_approvals'],
    ], (int)$user['id']);
    $pdo->commit();

    $queue = mg_share_market_approval_queue($pdo, $user);
    $created = null;
    foreach ($queue['items'] as $item) if ((string)$item['request_id'] === $requestId) $created = $item;
    mg_ok(['request' => $created, 'summary' => $queue['summary']], 'Approval request created. No share action was executed.', 201);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('warning', 'share_market.approval_request_denied', $e->getMessage(), [
        'manifest_id' => (string)($manifest['manifest_id'] ?? ''),
        'action' => (string)($manifest['action'] ?? ''),
    ], (int)$user['id']);
    mg_fail($e->getMessage(), 403);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'share_market.approval_request_failed', 'Unable to create Share Market approval request.', [
        'manifest_id' => (string)($manifest['manifest_id'] ?? ''),
        'exception_class' => $e::class,
    ], (int)$user['id']);
    mg_fail('Unable to create the approval request.', 500);
} finally {
    if ($lockAcquired) {
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        } catch (Throwable) {
        }
    }
}
