<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/approval-workflow.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.approval.decision', 'user:' . (int)$user['id'], 15, 300);

$requestId = trim((string)($input['request_id'] ?? ''));
$decision = strtolower(trim((string)($input['decision'] ?? '')));
$note = trim((string)($input['note'] ?? ''));
$password = (string)($input['password'] ?? '');
$confirmation = trim((string)($input['confirmation'] ?? ''));
$phrases = mg_share_market_approval_decision_phrases();

if ($requestId === '' || preg_match('/^[A-Za-z0-9-]{20,64}$/', $requestId) !== 1) mg_fail('Enter a valid approval request identifier.', 422);
if (!isset($phrases[$decision])) mg_fail('Select a valid approval decision.', 422);
if ($note === '' || strlen($note) > 1000) mg_fail('A decision note between 1 and 1,000 characters is required.', 422);
if (!hash_equals($phrases[$decision], $confirmation)) mg_fail('The typed confirmation phrase does not match.', 422);

$pdo = mg_db();
$lockName = 'sm-approval-request:' . substr(hash('sha256', $requestId), 0, 32);
$lockAcquired = false;

try {
    mg_share_market_approval_verify_password($pdo, (int)$user['id'], $password);
    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?,5)');
    $lockStmt->execute([$lockName]);
    $lockAcquired = (int)$lockStmt->fetchColumn() === 1;
    if (!$lockAcquired) throw new RuntimeException('This approval request is busy. Try again.');

    $pdo->beginTransaction();
    $item = mg_share_market_approval_find($pdo, $requestId);
    if (!$item) throw new InvalidArgumentException('Approval request not found.');

    $viewerId = (int)$user['id'];
    $requesterId = (int)$item['requester_user_id'];
    $isSuper = mg_share_market_admin_is_super_admin($user);
    $pending = in_array((string)$item['status'], mg_share_market_approval_pending_statuses(), true);
    $approvedIds = array_map(static fn(array $approval): int => (int)$approval['actor_user_id'], $item['approvals']);
    $manifest = is_array($item['manifest'] ?? null) ? $item['manifest'] : [];

    if ($decision === 'approve') {
        if (!$pending) throw new DomainException('Only pending requests can be approved.');
        if ($viewerId === $requesterId) throw new DomainException('The requesting administrator cannot approve this request.');
        if (in_array($viewerId, $approvedIds, true)) throw new DomainException('This administrator has already approved the request.');
        if (!empty($manifest['super_admin_required']) && !$isSuper) throw new DomainException('This critical request requires a super administrator approver.');
    } elseif ($decision === 'reject') {
        if (!$pending) throw new DomainException('Only pending requests can be rejected.');
        if ($viewerId === $requesterId) throw new DomainException('The requesting administrator must cancel rather than reject the request.');
    } elseif ($decision === 'cancel') {
        if (!$pending) throw new DomainException('Only pending requests can be cancelled.');
        if ($viewerId !== $requesterId && !$isSuper) throw new DomainException('Only the requester or a super administrator can cancel this request.');
    } elseif ($decision === 'escalate') {
        if (!$pending) throw new DomainException('Only pending requests can be escalated.');
    } elseif ($decision === 'request_freeze') {
        if (!$pending) throw new DomainException('Only pending requests can request a target freeze.');
        if (!$isSuper) throw new DomainException('Only a super administrator can request an emergency target freeze.');
    } elseif ($decision === 'record_expiry') {
        if ((string)$item['status'] !== 'expired') throw new DomainException('This request has not expired.');
        foreach ($item['timeline'] as $timeline) {
            if (($timeline['event_type'] ?? '') === 'share_market.approval.expired') throw new DomainException('The expiry event has already been recorded.');
        }
    }

    $eventType = match ($decision) {
        'approve' => 'share_market.approval.approved',
        'reject' => 'share_market.approval.rejected',
        'cancel' => 'share_market.approval.cancelled',
        'escalate' => 'share_market.approval.escalated',
        'request_freeze' => 'share_market.approval.freeze_requested',
        'record_expiry' => 'share_market.approval.expired',
    };

    $payload = [
        'request_id' => $requestId,
        'decision' => $decision,
        'actor_user_id' => $viewerId,
        'note' => $note,
        'previous_event_hash' => (string)($item['last_event_hash'] ?? ''),
        'created_at' => gmdate('c'),
        'execution_enabled' => false,
    ];
    mg_share_market_approval_append_event($pdo, $eventType, $payload, $viewerId);
    mg_audit($eventType, 'share_market_approval', [
        'request_id' => $requestId,
        'decision' => $decision,
        'action' => (string)($manifest['action'] ?? ''),
        'target_type' => (string)($manifest['target_type'] ?? ''),
        'target_id' => (string)($manifest['target_id'] ?? ''),
        'previous_event_hash' => (string)($item['last_event_hash'] ?? ''),
    ], $viewerId);
    $pdo->commit();

    $queue = mg_share_market_approval_queue($pdo, $user);
    $updated = null;
    foreach ($queue['items'] as $queueItem) if ((string)$queueItem['request_id'] === $requestId) $updated = $queueItem;
    mg_ok(['request' => $updated, 'summary' => $queue['summary']], 'Approval decision recorded. No share action was executed.');
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('warning', 'share_market.approval_decision_denied', $e->getMessage(), [
        'request_id' => $requestId,
        'decision' => $decision,
    ], (int)$user['id']);
    mg_fail($e->getMessage(), 403);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'share_market.approval_decision_failed', 'Unable to record Share Market approval decision.', [
        'request_id' => $requestId,
        'decision' => $decision,
        'exception_class' => $e::class,
    ], (int)$user['id']);
    mg_fail('Unable to record the approval decision.', 500);
} finally {
    if ($lockAcquired) {
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        } catch (Throwable) {
        }
    }
}
