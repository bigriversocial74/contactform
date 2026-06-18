<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management_roles.php';
require_once __DIR__ . '/_user_management_models.php';

function mg_admin_management_context(PDO $pdo, array $actor, int $userId): array
{
    $target = mg_admin_management_require_target($pdo, $userId);
    $self = (int)$actor['id'] === $userId;
    $targetSuper = (bool)$target['is_super_admin'];
    $actorSuper = mg_admin_management_actor_is_super($actor);
    $protected = $self || ($targetSuper && !$actorSuper);

    return [
        'capabilities' => [
            'status' => !$protected && mg_admin_management_actor_has($actor, 'admin.users.manage'),
            'roles' => !$protected && mg_admin_management_actor_has($actor, 'admin.roles.manage'),
            'models' => !$protected && mg_admin_management_actor_has($actor, 'admin.user_models.manage'),
            'sessions_view' => mg_admin_management_actor_has($actor, 'admin.sessions.view'),
            'sessions_revoke' => !$self && (!$targetSuper || $actorSuper) && mg_admin_management_actor_has($actor, 'admin.sessions.revoke'),
            'actor_super_admin' => $actorSuper,
            'target_super_admin' => $targetSuper,
            'self' => $self,
        ],
        'account_statuses' => [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'disabled', 'label' => 'Disabled / suspended'],
        ],
        'model_statuses' => [
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'disabled', 'label' => 'Disabled'],
            ['value' => 'suspended', 'label' => 'Suspended'],
            ['value' => 'rejected', 'label' => 'Rejected'],
            ['value' => 'revoked', 'label' => 'Revoked'],
        ],
        'roles' => mg_admin_management_roles($pdo, $userId),
        'models' => mg_admin_management_models($pdo, $userId),
    ];
}
