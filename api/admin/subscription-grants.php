<?php
declare(strict_types=1);

require_once __DIR__ . '/_subscription_grants.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    mg_fail('Method not allowed.', 405);
}

$actor = mg_require_permission('admin.subscriptions.manage');
$pdo = mg_db();
$input = $method === 'POST' ? mg_input() : [];
$targetUserId = mg_admin_user_detail_id($method === 'GET' ? ($_GET['user_id'] ?? null) : ($input['user_id'] ?? null));

if ($method === 'GET') {
    try {
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(mg_admin_subscription_grant_snapshot($pdo, $targetUserId), 'Subscription grant status loaded.');
    } catch (MgAdminSubscriptionGrantException $error) {
        mg_fail($error->getMessage(), $error->httpStatus);
    } catch (Throwable $error) {
        mg_security_log('error', 'admin.subscription_grant.read_failed', 'Unable to read complimentary subscription grants.', [
            'target_user_id' => $targetUserId,
            'exception_class' => $error::class,
        ], (int) $actor['id']);
        mg_fail('Unable to load subscription grant status.', 500);
    }
}

mg_require_csrf_for_write($input);
$action = strtolower(trim((string) ($input['action'] ?? '')));
$reason = trim((string) ($input['reason'] ?? ''));

try {
    $pdo->beginTransaction();

    if ($action === 'grant') {
        $result = mg_admin_subscription_grant_apply(
            $pdo,
            $actor,
            $targetUserId,
            (string) ($input['package_id'] ?? ''),
            (string) ($input['term'] ?? ''),
            isset($input['custom_end']) ? (string) $input['custom_end'] : null,
            $reason
        );
        $auditAction = 'admin_subscription_grant_created';
        $message = 'Complimentary subscription granted.';
    } elseif ($action === 'revoke') {
        $result = mg_admin_subscription_grant_revoke($pdo, $actor, $targetUserId, $reason);
        $auditAction = 'admin_subscription_grant_revoked';
        $message = 'Complimentary subscription revoked. Permission roles were preserved.';
    } else {
        throw new MgAdminSubscriptionGrantException('Choose grant or revoke.');
    }

    $pdo->commit();

    $metadata = [
        'target_user_id' => $targetUserId,
        'action' => $action,
        'reason' => $reason,
        'result' => $result,
    ];
    mg_audit($auditAction, 'platform_account_subscription', $metadata, (int) $actor['id']);
    mg_event('admin.subscription_grant.' . $action, $metadata + ['admin_user_id' => (int) $actor['id']], (int) $actor['id']);

    header('Cache-Control: private, no-store, max-age=0');
    mg_ok([
        'result' => $result,
        'snapshot' => mg_admin_subscription_grant_snapshot($pdo, $targetUserId),
    ], $message);
} catch (MgAdminSubscriptionGrantException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'admin.subscription_grant.write_failed', 'Complimentary subscription grant action failed.', [
        'target_user_id' => $targetUserId,
        'action' => $action,
        'exception_class' => $error::class,
    ], (int) $actor['id']);
    mg_fail('Unable to update the complimentary subscription.', 500);
}
