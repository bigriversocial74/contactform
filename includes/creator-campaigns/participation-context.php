<?php
declare(strict_types=1);

function mg_creator_campaign_participation_required_tables(): array
{
    return [
        'creator_campaign_applications',
        'creator_campaign_application_answers',
        'creator_campaign_invitations',
        'creator_campaign_participants',
        'creator_campaign_participation_events',
    ];
}

function mg_creator_campaign_participation_assert_schema(PDO $pdo): void
{
    $required = mg_creator_campaign_participation_required_tables();
    $placeholders = implode(',', array_fill(0, count($required), '?'));
    $stmt = $pdo->prepare(
        "SELECT table_name FROM information_schema.tables
         WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})"
    );
    $stmt->execute($required);
    $found = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $missing = array_values(array_diff($required, $found));
    if ($missing !== []) {
        throw new RuntimeException(
            'Creator Participation schema is incomplete. Import database/20260721_creator_campaign_participation_v3.sql.'
        );
    }
}

function mg_creator_campaign_creator_has_platform_permission(PDO $pdo, array $user, string $permission): bool
{
    if (mg_creator_campaign_is_admin_actor($user)) return true;
    if (function_exists('mg_api_user_has_permission') && mg_api_user_has_permission($user, $permission)) return true;
    $userId = (int) ($user['id'] ?? 0);
    if ($userId < 1) return false;
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM user_roles ur
         INNER JOIN role_permissions rp ON rp.role_id=ur.role_id
         INNER JOIN permissions p ON p.id=rp.permission_id
         WHERE ur.user_id=? AND p.slug=? LIMIT 1'
    );
    $stmt->execute([$userId, $permission]);
    return (bool) $stmt->fetchColumn();
}

function mg_creator_campaign_creator_context(PDO $pdo, array $user, string $permission): array
{
    mg_creator_campaign_participation_assert_schema($pdo);
    $userId = (int) ($user['id'] ?? 0);
    if ($userId < 1) throw new DomainException('Authentication is required.');

    $status = $pdo->prepare('SELECT status FROM users WHERE id=? LIMIT 1');
    $status->execute([$userId]);
    if ((string) ($status->fetchColumn() ?: '') !== 'active') {
        throw new DomainException('An active user account is required.');
    }

    $eligibility = mg_creator_campaign_require_creator_eligibility($pdo, $userId);
    if (!mg_creator_campaign_is_admin_actor($user) && function_exists('mg_current_active_model_context')) {
        $activeModel = mg_current_active_model_context($userId);
        if ($activeModel !== 'creator') {
            throw new DomainException('Switch to the Creator model before managing creator campaign participation.');
        }
    }
    if (!mg_creator_campaign_creator_has_platform_permission($pdo, $user, $permission)) {
        throw new DomainException('Creator campaign permission is not enabled for this account.');
    }

    return [
        'actor_user_id' => $userId,
        'creator_user_id' => $userId,
        'creator_profile_id' => (int) $eligibility['creator_profile_id'],
        'creator_profile_public_id' => (string) ($eligibility['creator_profile_public_id'] ?? ''),
    ];
}

function mg_creator_campaign_participation_merchant_context(
    PDO $pdo,
    array $user,
    string $permission,
    ?int $requestedWorkspaceId = null
): array {
    mg_creator_campaign_participation_assert_schema($pdo);
    return mg_creator_campaign_actor_context($pdo, $user, $permission, $requestedWorkspaceId);
}

function mg_creator_campaign_participation_require_campaign_open(array $campaign, string $operation): void
{
    if (mg_creator_campaign_participation_campaign_is_closed($campaign)) {
        throw new DomainException("This campaign is closed and cannot {$operation}.");
    }
}

function mg_creator_campaign_participation_require_expected_lock(array $row, int $expectedLock): void
{
    if ($expectedLock < 1) throw new InvalidArgumentException('expected_lock_version is required.');
    if ((int) ($row['lock_version'] ?? 0) !== $expectedLock) {
        throw new DomainException('This participation record changed in another request. Reload and try again.');
    }
}
