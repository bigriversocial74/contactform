<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management_common.php';
require_once __DIR__ . '/content-review/_actions.php';

function mg_admin_management_account_action(PDO $pdo, array $actor, int $userId, string $action, string $reason): array
{
    if (!in_array($action, ['suspend_user', 'reactivate_user'], true)) {
        throw new InvalidArgumentException('Invalid account action.');
    }

    $target = mg_admin_management_require_target($pdo, $userId, true);
    mg_admin_management_guard_target($actor, $target, 'admin.users.manage');

    $result = mg_content_review_set_user_status(
        $pdo,
        ['subject_user_id' => $userId],
        $action,
        (int)$actor['id']
    );

    if ($action === 'suspend_user') {
        mg_revoke_user_sessions($userId);
    }

    return [
        'action' => $action,
        'from' => (string)$result['previous'],
        'to' => (string)$result['result'],
        'reason' => $reason,
    ];
}
