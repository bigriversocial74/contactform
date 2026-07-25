<?php
declare(strict_types=1);

require_once __DIR__ . '/_public_donations_operations_projection.php';

mg_require_method('POST');
$actor = mg_admin_public_donations_require_user();
$actorId = (int)$actor['id'];
$input = mg_input();
mg_require_csrf_for_write($input);
mg_rate_limit('admin.public_donations_operations.write', 'user:' . $actorId, 30, 60);

$action = strtolower(trim((string)($input['action'] ?? '')));

try {
    $pdo = mg_db();
    if (in_array($action, ['update_rollout', 'return_to_environment'], true)) {
        $result = [
            'rollout' => mg_admin_public_donations_update_rollout($pdo, $actor, $input),
        ];
    } elseif ($action === 'reconcile') {
        $result = [
            'reconciliation' => mg_admin_public_donations_reconcile($pdo, $actor, $input),
        ];
    } else {
        throw new MgAdminPublicDonationsOperationsException('Invalid Public Donations operations action.');
    }

    $result['operations'] = mg_admin_public_donations_read_projection($pdo, $actor);
} catch (MgAdminPublicDonationsOperationsException $error) {
    mg_security_log('warning', 'admin.public_donations_operations.rejected', 'Public Donations operations action was rejected.', [
        'action' => $action,
        'reason' => $error->getMessage(),
    ], $actorId);
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_fail_unexpected(
        $error,
        'admin.public_donations_operations.action_failed',
        'Unable to complete the Public Donations operations action.',
        500,
        ['action' => $action],
        $actorId
    );
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok($result, 'Public Donations operations action completed.');
